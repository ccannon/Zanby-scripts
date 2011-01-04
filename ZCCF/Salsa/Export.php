<?php
/**
 * Z1Sky -> Salsa export class.
 * @todo This class can be optimized for performance at least at following ways:
 * 1) load all supporters & groups from Salsa first with single query and then
 * parse it here to match against Z1Sky users/groups,
 * 2) check if Z1Sky user or group is really changed and save them only if they are,
 * 3) remove only links, that are really absent on Z1Sky side.
 * @package Z1SKY
 * @subpackage Z1SKY_Salsa
 */
class ZCCF_Salsa_Export extends Z1SKY_Salsa_Export
{
    public $mainFamily = null;
    private static $_instance;
    private $emailTriggerId = null;
    
    public function __construct($salsaURL, $salsaUser, $salsaPassword)
    {
        $tableCreation = "CREATE TABLE IF NOT EXISTS `z1sky_salsa__sync` ( `salsa_id` int(11) unsigned NOT NULL, `entity_id` int(11) unsigned NOT NULL, `entity_type_id` int(11) unsigned NOT NULL, `http_context` varchar(10) NOT NULL default '', PRIMARY KEY  (`entity_id`,`entity_type_id`,`http_context`), KEY `Ent` (`entity_type_id`,`entity_id`));";
        $_db = Zend_Registry::get('DB');
        $_db ->query($tableCreation);
        parent::__construct($salsaURL, $salsaUser, $salsaPassword);
    }

    public static function exportData($salsaURL, $salsaUser, $salsaPassword, $fetchAll = true, $nonDestructive = true)
    {
        $export = new ZCCF_Salsa_Export($salsaURL, $salsaUser, $salsaPassword);
        $export->setFetchAll($fetchAll)
            ->setNonDestructive($nonDestructive)
            ->loadAllObjectsFromZanby()
            ->fetchAllSalsaKeys()
            ->saveAllObjectsToSalsa()
            ->saveAllObjectsLinks();
    }

    public function loadAllObjectsFromZanby()
    {
	    $userList = new Warecorp_User_List();
	    $userList->setStatus(array('pending','active','blocked','deleted') );
        if (self::getContext() !== null){
            $_db = Zend_Registry::get('DB');
            $implUsersList = $_db ->fetchAssoc('select * from zanby_users__accounts');
            $implUsersList[0] = 0;
            $userList->addWhere('id in (?)', array_values($implUsersList));
        }
	    $this->_users = $userList->getList();

        if (self::getContext() !== null) {
            $mainGroup = Warecorp_Group_Factory::loadByGroupUIDWithoutException(self::getContext());
            if ( $mainGroup !== null && $mainGroup->getId() !== null) {
                $this->_groups = $mainGroup->getGroups()->setTypes(array('simple', 'family'))->getList();
            }
            else{
                throw new Zend_Exception("Unknown group family!");
            }
        } else {
            $groupList = new Warecorp_Group_List();
            $groupList->setTypes(array("simple", "family"));
            $this->_groups = $groupList->getList();
        }
	    return $this;
    }


    
    protected function saveSalsaUserFromZanby(Warecorp_User $user)
    {
        $profile = $user->getProfile();
        $properties = array(
            'Email'                 => $user->getEmail(),
            'First_Name'            => $user->getFirstname(),
            'Last_Name'             => $user->getLastname(),
            'Zip'                   => $user->getZipcode(),
            'State'                 => $user->getState()->code,
            'Country'               => $user->getCountry()->code,
            'Timezone'              => $user->getTimezone(),
            'ccf_user_id'           => $user->getId(),
            'Longitude'             => $user->getLongitude(),
            'Latitude'              => $user->getLatitude(),
            'ccf_status'            => $user->getStatus(),
            'confirmation_status'   => (bool)($user->getConfirmationStatus() == '1')
        );

        if ($profile) {
            $contactTypes = 'No';
            if ($profile->getcontactphone() == 1
                    || $profile->getcontactemail() == 1
                    || $profile->getcontactletter() == 1
            ) {
                $contactTypes = 'Yes';
            } else {
                $contactTypes = 'No';
            }


            if ($profile->getUseWorkAddress() == 'Home') {
                $properties = array_merge($properties, array(
                    'home_address'  => $profile->getHomeAddressLine1(),
                    'home_unit'     => $profile->getHomeAddressLine2(),
                    'home_city'     => $profile->getHomeCity(),
                    'home_state'    => $profile->getHomeState(),
                    'home_zip'      => $user->getZipcode(),
                    'home_county'   => $profile->getDistrict(),
                ));
            } else {
                $properties = array_merge($properties, array(
                    'work_address'  => $profile->getHomeAddressLine1(),
                    'work_unit'     => $profile->getHomeAddressLine2(),
                    'work_city'     => $profile->getHomeCity(),
                    'work_state'    => $profile->getHomeState(),
                    'work_zip'      => $user->getZipcode(),
                    'work_county'   => $profile->getDistrict(),
                ));
            }

            if  ($profile->getWorkZip()!= '') {
                $properties = array_merge($properties, array(
                    'use_other_address' => "Yes",
                    'other_address'     => $profile->getWorkAddressLine1(),
                    'other_unit'        => $profile->getWorkAddressLine2(),
                    'other_city'        => $profile->getWorkCity(),
                    'other_state'       => $profile->getWorkState(),
                    'other_zip'         => $profile->getWorkZip(),
                ));
            }

            $properties = array_merge($properties, array(
                'preferred_address'         => $profile->getUseWorkAddress(),
		        'Street'                    => $profile->getHomeAddressLine1(),
  		        'Street_2'                  => $profile->getHomeAddressLine2(),
                'City'                      => $profile->getHomeCity(),
                'State'                     => $profile->getHomeState(),
                'age_group'                 => $profile->getAge(),
                'MI'                        => $profile->getMiddleName(),
                'Email_Preference'          => $profile->getEmailFormat(),
                'may_be_contacted'          => $contactTypes,
                'Receive_Phone_Blasts'      => $profile->getcontactphone(),
                'Receive_Email'             => $profile->getcontactemail(),
                
                //'workaddressline1'        = => $profile->getWorkAddressLine1(),
                //'workaddressline2'        = => $profile->getWorkAddressLine2(),
                //'workcity'                = => $profile->getWorkCity(),
                //'workstate'               = => $profile->getWorkState(),
                //'workzip'                 = => $profile->getWorkZip(),
                //'ethnicity'               = => $profile->getEthnicity(),

                'racial_heritage'           => $profile->getEthnicity(),
                'employment_status'         => $profile->getEmployment(),
                'education'                 => $profile->getEducation(),
                'family_description'        => $profile->getFamily(),
                //'childathome'             = => $profile->getChildathome(),
                //'childouthome'            = => $profile->getChildouthome(),
                'gender'                    => $profile->getGender(),

                'volunteer'                 => (bool)($profile->getVolunteer() == '1'),
                'hosting_private'           => (bool)($profile->getMeetingsathome() == '1'),
                'hosting_public'            => (bool)($profile->getMeetingsatpublic() == '1'),
                'facilitate'                => (bool)($profile->getfacilitate() == '1'),
                'emailformat'               => $profile->getemailformat(),
                'how_did_you_learn_about'   => $profile->gethowlearn(),
                'organization_partner'      => $profile->getpartofgroup(),
                'cancontact'                => $contactTypes,
                'gender'                    => $profile->getgender(),
                'video_completion'          => $profile->gettopicvideo(),
                'useworkaddress'            => $profile->getuseworkaddress(),
                'district'                  => $profile->getDistrict(),
                'middlename'                => $profile->getMiddlename(),
                'snail_mail'                => $profile->getcontactletter(),
            	'survey_completion'         => $profile->getSurveyCompleteDate(),
            	'survey_status'             => $profile->getSurveyStatus(),
                'survey_responce_id'        => $profile->getSurveyId(),
                'username'                  => $user->getLogin(),
                // @todo Middle name
                
                //'MI'                      = => $profile->getMiddleName(),
		        'Phone'                     => $profile->getTelephoneNumber(),
            ));
        }

	    return $this->_sapi->saveObject("supporter", $this->_salsaUserKeys[$user->getId()], array_filter($properties));
    }

    /**
     * Save data links into Salsa.
     * @return Z1SKY_Salsa_Export
     */
    public function saveAllObjectsLinks()
    {
	$this->log("\nSaving user to group links into Salsa...");
	$newLinksCount = 0;
	$delLinksCount = 0;
	$oldLinksCount = 0;
	$customFieldsCount = 0;

	foreach ($this->_users as $user)
	{
	    $userId    = $user->getId();
	    $userEmail = $user->getEmail();
	    $isHost    = false;
	    if (!isset($this->_salsaUserKeys[$userId]))
	    {
		$this->log("Error processing user $userId links (email=$userEmail): missing user Salsa key! Going on...");
		continue;
	    }

	    $userKey    = $this->_salsaUserKeys[$userId];

	    $this->log("Loading existing links for user $userId (key=$userKey)...");
	    $groupList  = $user->getGroups()->getList();
	    $salsaLinks = $this->_sapi->getObjects("supporter_groups", "supporter_KEY=$userKey");
	    $linkKeys   = array();
	    if ($salsaLinks)
	    {
		foreach ($salsaLinks as $link)
		{
		    $linkKeys[(int)$link->groups_KEY] = (int)$link->key;
		}
	    }

	    $exportedGroups = array();
	    foreach ($groupList as $group)
	    {
		if (!isset($this->_salsaGroupKeys[$group->getId()]))
		{
		    $this->log("Error processing user $userId (key=$userKey) to group " . $group->getId() . " (name=" . $group->getName() . ") link: missing group Salsa key! Going on...");
		    continue;
		}

		$host = $group->getHost();
		if ($host && $host->getId() == $userId)
		{
		    $isHost = true;
		}

		$groupId   = $group->getId();
		$groupKey  = $this->_salsaGroupKeys[$groupId];
		$groupName = $group->getName();
		if (isset($linkKeys[$groupKey]))
		{
		    $this->log("User $userId (key=$userKey) to group $groupId (key=$groupKey) link under key $linkKeys[$groupKey] is intact: not modified.");
		    unset($linkKeys[$groupKey]);
		    $exportedGroups[] = $groupName;
		    $oldLinksCount++;
		    continue;
		}

		$linkKey  = $this->_sapi->saveObject("supporter_groups", null, array("supporter_KEY" => $userKey, "groups_KEY" => $groupKey));
		if ($linkKey)
		{
		    $this->log("Saving user $userId (key=$userKey) to group $groupId (key=$groupKey) link under key $linkKey.");
		    $newLinksCount++;
		    $exportedGroups[] = $groupName;
		}
		else
		{
		    $this->log("Error saving user $userId (key=$userKey) to group $groupId (key=$groupKey)! Going on...");
		}
	    }

	    if ($linkKeys)
	    {
		if ($this->_nonDestructive)
		{
		    $this->log("Warning: non-destructive mode prevents removing of " . count($linkKeys) . " obsolete links for user $userId (key=$userKey).");
		}
		else
		{
		    $this->log("Removing old user links for user $userId (key=$userKey)...");
		    foreach ($linkKeys as $link)
		    {
			$this->_sapi->deleteObject("supporter_groups", $link);
			$delLinksCount++;
		    }
		}
	    }

            $profile = $user->getProfile();

	    $customKey = $this->saveCustomFieldsForUser($userKey, array(
		"age" => '12' //$profile->getAge(),
	    ));
	    if ($customKey)
	    {
		$this->log("Saving custom fields for user $userId (key=$userKey) under key $customKey.");
		$customFieldsCount++;
	    }
	    else
	    {
		$this->log("Error setting custom fields for user $userId (key=$userKey)! Going on...");
	    }
	}

	$this->log("Exported $newLinksCount new links, removed $delLinksCount deprecated links, $oldLinksCount links not modified, total existing links after export: " . ($newLinksCount + $oldLinksCount) . ".");
	$this->log("Exported $customFieldsCount custom field entries.");
	return $this;
    }
    
}

