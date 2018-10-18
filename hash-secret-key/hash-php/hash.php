function hashSecretKey($secretKey, $serviceId){
        $ts = time();
        $hashed = hash_hmac('sha256', $serviceId.$ts, $secretKey, false);
        
        return array('timestamp'=>$ts, 'hash'=>$hashed);
    }
