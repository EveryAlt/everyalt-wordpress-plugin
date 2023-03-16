<?php

class Every_Alt_Curls{

    protected $token;

    public function __construct($token) {
		$this->token = $token;
	}

    
    public function every_alt_generate_alt($url){

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://everyalt.com/wp-json/api/v1/altimages',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('url' => $url),
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$this->token
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response);
    
    
    }

    public function every_alt_get_available_tokens(){

        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://everyalt.com/wp-json/api/v1/get_available_tokens',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer '.$this->token,
        ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        

        return json_decode($response);

       

    }
}