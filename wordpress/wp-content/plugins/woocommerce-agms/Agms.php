<?php
/**
* Agms
*
* @class 		Agms
* @version		0.1.0
* @author      Maanas Royy
*/

class Agms
{
    /**
     * Convert array to xml string
     *
     * @return string
     */
    public static function buildRequestBody($request, $op='ProcessTransaction')
    {
        /*
         * Resolve object parameters
         */
        switch ($op) {
            case 'ProcessTransaction':
                $param = 'objparameters';
                break;

        }

        $xmlHeader = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
xmlns:xsd="http://www.w3.org/2001/XMLSchema"
xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <' . $op . ' xmlns="https://gateway.agms.com/roxapi/">
      <' . $param . '>';
        $xmlFooter = '</' . $param . '>
    </' . $op . '>
  </soap:Body>
</soap:Envelope>';

        $xmlBody = '';
        foreach ($request as $key => $value) {
            $xmlBody = $xmlBody . "<$key>$value</$key>";
        }
        $payload = $xmlHeader . $xmlBody . $xmlFooter;
        return $payload;

    }

    /**
     * Builds header for the Request
     *
     * @return array
     */
    public static function buildRequestHeader($op='ProcessTransaction')
    {
        return array(
            "Accept" => "application/xml",
            "Content-type" => "text/xml; charset=utf-8",
            "SOAPAction" => "https://gateway.agms.com/roxapi/" . $op
        );
    }

    /**
     * Parse response from Agms Gateway
     *
     * @return array
     */
    public static function parseResponse($response, $op)
    {
        $xml = new \SimpleXMLElement($response);
        $xml = $xml->xpath('/soap:Envelope/soap:Body');
        $xml = $xml[0];
        $data = json_decode(json_encode($xml));
        $opResponse = $op . 'Response';
        $opResult = $op . 'Result';
        $arr = Agms::object2array($data->$opResponse->$opResult);
        return $arr;
    }

    /**
     * Convert object to array
     *
     * @return array
     */
    private static function object2array($data)
    {
        if (is_array($data) || is_object($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = Agms::object2array($value);
            }
            return $result;
        }
        return $data;
    }
}