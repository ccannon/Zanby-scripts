<?php
/**
 * Warecorp FRAMEWORK
 * @package    Warecorp_User
 * @copyright  Copyright (c) 2008
 * @author Andrey Kondratiev
 */

class ZCCF_Votervoice_Client
{
    static private $authKey = "yQpwsNif5xOOnSlmZECyQ7RZXTcs5+InYT2gIDgcXjk8AXHsNcm95Q==";


    public static function validateDistrictByAddress($params) {
        if ( trim($params['City']) == '' || trim($params['State']) == '' || trim($params['Zip']) == '' ) return true;
        if ( trim($params['Address']) == '' && trim($params['Address2']) == '' ) return true;
        
        $client = new Zend_Soap_Client('http://services.votervoice.net/AdvocacyWS/Directory.asmx?WSDL');
        $addressArray = $params;

        $data = array('strEncryptedAuthKey' => self::$authKey, 'inAddr' => $addressArray);


        try {
            $result = $client->GetDistricts((object)$data);
        } catch (Exception $e) {
            throw new Exception($e);
        }
        /*
         * Next condition must be uncommented for production.  
         */

        //var_dump($result); exit();
        
        if ( count($result->legDists->CountyGovtDistrict) && $result->outAddr->County == 'King' ) {
            return false;
        }
        return true;

        return false;

    }

    public static function getDistrictByAddress($address, $address2, $city, $state, $zip, $zipExt = null, $county = null) {
        $client = new Zend_Soap_Client('http://services.votervoice.net/AdvocacyWS/Directory.asmx?WSDL');
        $addressArray = array();
        $addressArray['Address'] = $address;
        $addressArray['Address2'] = $address2;
        $addressArray['City'] = $city;
        $addressArray['State'] = $state;
        $addressArray['Zip'] = $zip;

        if ($zipExt !== null) {
            $addressArray['ZipExt'] = $zipExt;
        }

        if ($county !== null) {
            $addressArray['County'] = $county;
        }

        $data = array('strEncryptedAuthKey' => self::$authKey, 'inAddr' => $addressArray);

        try {
            $result = $client->GetDistricts((object)$data);
        } catch (Exception $e) {
            throw new Exception($e);
        }

        return $result->legDists->CountyGovtDistrict;
    }

}
