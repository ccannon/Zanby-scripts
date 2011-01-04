<?php
/**
 * Interface to Salsa API.
 *
 * Simple usage example:
 *
 * $sapi = new Z1SKY_Salsa_API("http://sandbox.salsalabs.com");
 * if (!$sapi->authenticate())
 *	throw new Exception($sapi->getErrorMessage());
 *
 * $result = sapi->getCount("supporter");
 * if ($result !== false)
 * {
 *      echo "Total number of supporters: " . $result;
 * } else {
 *      echo "Error occurred: " . $result->getErrorMessage();
 * }
 * 
 * @package Z1SKY
 * @subpackage Z1SKY_Salsa
 */
class ZCCF_Salsa_API extends Z1SKY_Salsa_API
{
    
}
?>
