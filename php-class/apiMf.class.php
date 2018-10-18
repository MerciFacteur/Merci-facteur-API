<?php

class apiMerciFacteur {

    var $serviceId;

    public function __construct($serviceId){
        
        $this->serviceId = $serviceId;
    }
    
    
    /**
     * hasher votre secret key
     * @param string $secretKey : votre secret key
     * @return array : ['timestamp'=>'int timestamp', 'hash'=>'strig hashed']
     */
    private function hashSecretKey($secretKey){
        $ts = time();
        $hashed = hash_hmac('sha256', $this->serviceId.$ts, $secretKey, false);
        
        return array('timestamp'=>$ts, 'hash'=>$hashed);
    }
    
    
    
    
    
    /**
     * Demander un Access Token. Vous devez stocker ce token en local pour l'utiliser � chaque op�ration, et renouveler la demande lorsque le token est expir� ou pour ajouter de nouvelles IP.
     * @param string $secret : cl� secr�te, disponible dans votre compte Merci facteur Pro
     * @param array $ipArray : Tableau contenant les IP des serveurs � autoriser sur ce token array[$ip1, $ip2, $ip3, ...]
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "token"=>null|string, "expire" => null|timestamp]
     */
    public function getAccessToken($secret,$ipArray)
    {
        if(!is_array($ipArray))
        {return array('success'=>false,'error'=>'IP_MUST_BE_ARRAY');}
        
        $ipArray = implode(';',$ipArray);
        
        $hashArray = $this->hashSecretKey($secret);
        
        $headers = array(
            'ww-service-signature:' . $hashArray['hash'],
            'ww-timestamp:' . $hashArray['timestamp'],
            'ww-service-id:' . $this->serviceId,
            'ww-authorized-ip:' . $ipArray
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/getToken');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    
    /**
     * Cr�er un nouvel utilisateur
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param array $arrayInfosUser : Tableau des informations de l'utilisateur ['email'=>$email,'firstName'=>$firstName,'lastName'=>$lastName] ; $email doit �tre unique.
     * @return array : ["success"=>false|true, "user_id"=>null|int UserId, "error"=>null|code_erreur]
     */
    public function setNewUser($accessToken,$arrayInfosUser)
    {
        if(!is_array($arrayInfosUser))
        {return array('success'=>false,'error'=>'INFOS_MUST_BE_ARRAY');}
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $arrayInfosUser['firstName'] = utf8_encode($arrayInfosUser['firstName']);
        $arrayInfosAdress['lastName'] = utf8_encode($arrayInfosUser['lastName']);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/setUser');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST , true);
        curl_setopt($curl, CURLOPT_POSTFIELDS  , array('emailUser'=>$arrayInfosUser['email'],'first_name'=>$arrayInfosUser['firstName'],'last_name'=>$arrayInfosUser['lastName']));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    

    
    
    
    
    
   /**
    * Supprimer un utilisateur. Cela ne supprimer pas ses adresses, ni ses courriers qui sont conserv�s sur votre compte Merci facteur Pro
    * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
    * @param int $idUser : user ID de l'utilisateur � supprimer
    * @return array : ["success"=>true|false, "error"=>null|code_erreur]
    */
    public function deleteUser($accessToken,$idUser)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/deleteUser?idUser='.$idUser);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    
    /**
     * R�cup�rer le user ID � partir de l'adresse email. Pour utiliser cette fonction le moins possible, stockez les userId en local.
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param string $emailUser : email de l'utilisateur
     * @return array : ["success"=>false|true, "user_id"=>null|int UserId, "error"=>null|code_erreur]
     */
    public function getUserId($accessToken,$emailUser)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/getUserId?emailUser='.urlencode($emailUser));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    /**
     * Modifier les informations d'un utilisateur
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idUser : user ID de l'utilisateur � modifier
     * @param array $arrayInfosUser : Tableau des informations de l'utilisateur ['email'=>$email,'firstName'=>$firstName,'lastName'=>$lastName] ; $email doit �tre unique.
     * @return array : ["success"=>true|false, "error"=>null|code_erreur]
     */
    
    public function updateUser($accessToken, $idUser, $arrayInfosUser)
    {
        if(!is_array($arrayInfosUser))
        {return array('success'=>false,'error'=>'INFOS_MUST_BE_ARRAY');}
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $arrayInfosUser['firstName'] = utf8_encode($arrayInfosUser['firstName']);
        $arrayInfosAdress['lastName'] = utf8_encode($arrayInfosUser['lastName']);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/updateUser?idUser='.$idUser);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST , true);
        curl_setopt($curl, CURLOPT_POSTFIELDS  , array('emailUser'=>$arrayInfosUser['email'],'first_name'=>$arrayInfosUser['firstName'],'last_name'=>$arrayInfosUser['lastName']));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    /**
     * Lister tous les pays possibles, avec leur orthographe conforme
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param array $zone : Zones geographiques � extraire : ['fr','om1','om2','z1','z2'] avec fr = France m�tropolitaine / OM1 = GUADELOUPE, GUYANE FRANCAISE, MARTINIQUE, MAYOTTE, REUNION, SAINT BARTHELEMY, SAINT MARTIN, ST-PIERRE-MIQUELON / OM2 = CLIPPERTON, NOUVELLE CALEDONIE, POLYNESIE FRANCAISE, TERRES AUSTRALES FR, WALLIS ET FUTUNA / Z1 : UE sauf France, Z2 : Reste du monde
     * @return array : ["success"=>true|false, "error"=>null|code_erreur, "country"=>null|['FRANCE','ESPAGNE','BRESIL',etc.]]
     */
    public function getCountry($accessToken,$zone)
    {
        if(!is_array($zone))
        {return array('success'=>false,'error'=>'ZONE_MUST_BE_ARRAY');}
        
        $zone = implode('&zone[]=',$zone);
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/getCountry?zone[]='.$zone);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    /**
     * Cr�er une nouvelle adresse d'exp�diteur ou de destinataire pour un utilisateur.
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idUser : user ID de l'utilisateur
     * @param string $type : Type d'adresse expediteur (=exp) ou type d'adresse destinataire (=dest)
     * @param array $arrayInfosAdress : Informations de l'adresse � cr�er ['type'=>'exp|dest','logo'=>'URL logo (uniquement pour exp)','civilite'=>'','nom'=>'','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'','ville'=>'','pays'=>''] / Sont obligatoires : (nom et/ou soci�t�), (cp), (ville), (pays) / pays doit �tre avec une orthographe conforme cf. getCountry() / Si une infos est inutilis�e, la garder dans le tableau en string vide
     * @return array : ["success"=>false|true, "adress_id"=>null|int adresseId, "error"=>null|code_erreur]
     */
    public function setNewAdress($accessToken, $idUser, $type, $arrayInfosAdress)
    {
        if(!is_array($arrayInfosAdress))
        {return array('success'=>false,'error'=>'INFOS_MUST_BE_ARRAY');}
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        foreach ($arrayInfosAdress as $key => $value) {
            $arrayInfosAdress[$key] = utf8_encode($value);
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/setNewAdress?idUser='.$idUser.'&type='.$type);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST , true);
        curl_setopt($curl, CURLOPT_POSTFIELDS  , array('adress'=>json_encode($arrayInfosAdress)));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    /**
     * Modifier une adresse existante
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idAdress : id de l'adresse � modifier
     * @param array : Informations de l'adresse � modifier ['logo'=>'URL logo (uniquement pour exp)','civilite'=>'','nom'=>'','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'','ville'=>'','pays'=>''] / Sont obligatoires : (nom et/ou soci�t�), (cp), (ville), (pays) / pays doit �tre avec une orthographe conforme cf. getCountry() / Si une infos est inutilis�e, la garder dans le tableau en string vide
     * @return array : ["success"=>false|true, "error"=>null|code_erreur]
     */
    public function updateAdress($accessToken, $idAdress, $arrayInfosAdress)
    {
        if(!is_array($arrayInfosAdress))
        {return array('success'=>false,'error'=>'INFOS_MUST_BE_ARRAY');}
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        foreach ($arrayInfosAdress as $key => $value) {
            $arrayInfosAdress[$key] = utf8_encode($value);
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/updateAdress?idAdress='.$idAdress);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST , true);
        curl_setopt($curl, CURLOPT_POSTFIELDS  , array('adress'=>json_encode($arrayInfosAdress)));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    
    /**
     * Lister les adresses d'un utilisateur
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idUser : user ID de l'utilisateur dont on veux lister les adresses
     * @param string $type : exp ou dest suivant si vous souhaitez extraire les exp�diteurs ou les destinataires.
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, adress =>null|[0 => ['id'=>'int adress ID','civilite'=>'','nom'=>'','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'','ville'=>'','pays'=>''],...]]
     */
    public function listAdress($accessToken, $idUser, $type)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/listAdress?idUser='.$idUser.'&type='.$type);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    
    
    /**
     * Valider l'envoi d'un courrier : ATTENTION, cette op�ration g�n�re un courrier qui sera d�bit� de votre compte, imprim� et post�. Si vous effectuez un test, merci de prendre contact avec le SAV de Merci facteur pour demander l'annulation de la commande.
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idUser : user ID de l'utilisateur qui envoi le courrier
     * @param array $adress : tableau contenant les id des adresse d'exp�diteur et de destinataire(s) : ['exp'=>12,'dest'=>[23,25,94]]
     * @param array $files : tableau du/des url fichier(s) PDF � envoyer : ['https://mysite/doc/file1.pdf', 'https://mysite/doc/file2.pdf']
     * @param string $modeEnvoi : Mode d'envoi suivi|lrar|normal
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "envoi_id"=>null|[int], "price"=>null|['total'=>['ht'=>float, 'ttc'=>float],'detail'=>['affranchissement'=>float]], "resume"=>['nb_dest'=>int nb destinataires, 'nb_page'=>int nb pages par courrier]] Il est conseill� de sauvegarder en local l'id des envois.
     */
    public function sendCourrier($accessToken, $idUser, $adress, $files, $modeEnvoi)
    {
        if(!is_array($adress))
        {return array('success'=>false,'error'=>'ADRESS_MUST_BE_ARRAY');}
        
        if(!is_array($files))
        {return array('success'=>false,'error'=>'FILES_MUST_BE_ARRAY');}
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        foreach ($files as $key => $value) {
            $files[$key] = utf8_encode($value);
        }
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/sendCourrier');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST , true);
        curl_setopt($curl, CURLOPT_POSTFIELDS  , array('idUser'=>$idUser, 'adress'=> json_encode($adress), 'files'=>json_encode($files), 'modeEnvoi'=>$modeEnvoi));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }

    /**
     * Lister les 50 derniers envois d'un utilisateur
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idUser : user Id de l'utilisateur en question
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, envois =>null|["idEnvoi"=>int, "statut"=>string, "nbPage"=>int, "nbDest"=>int, "modeEnvoi"=>lrar|suivi|normal, "date"=>timestamp, "amount"=>["contenu"=>["ht"=>float], "affranchissement"=>float, "total"=>["ht"=>float]]],...], "idExp"=>int, "idDest"=>[id1, id2, ...]]
     */
    public function listEnvois($accessToken, $idUser)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/listEnvois?idUser='.$idUser);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
    
    /**
     * Obtenir le d�tail d'un envoi en particulier (un envoi peut �tre compos� de plusieurs destinataires et donc de plusieurs courriers
     * @param string $accessToken : Access Token que vous avez demand� avec getAccessToken() ou que vous avez stock� en local
     * @param int $idEnvoi : id de l'envoi en question
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, envois =>null|["general"=>["idEnvoi"=>int, "statut"=>string, "nbPage"=>int, "nbDest"=>int, "modeEnvoi"=>lrar|suivi|normal, "date"=>timestamp, "amount"=>["contenu"=>["ht"=>float], "affranchissement"=>float, "total"=>["ht"=>float]]],...], "idExp"=>int, "idDest"=>[id1, id2, ...],"detail"=>["ref"=>ref_courrier,"dest"=>[adresse complete du destinataire]]]]
     */
    public function getEnvoi($accessToken, $idEnvoi)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/getEnvoi?idEnvoi='.$idEnvoi);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);
        
        //uncomment to debug
        //return array($err,$httpcode);
        
        return json_decode($response,true);
    }
}

