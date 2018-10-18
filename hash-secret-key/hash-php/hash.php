
function hashSecretKey($secretKey){
        $ts = time();
        $hashed = hash_hmac('sha256', $this->serviceId.$ts, $secretKey, false);
        
        return array('timestamp'=>$ts, 'hash'=>$hashed);
    }
