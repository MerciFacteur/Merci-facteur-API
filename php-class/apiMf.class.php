<?php

class apiMerciFacteur {

    var $serviceId;

    /** Timeout de connexion, en secondes. Sans lui, un incident réseau bloque PHP jusqu'au max_execution_time. */
    public $connectTimeout = 10;

    /** Timeout total de la requête, en secondes. */
    public $timeout = 120;

    public function __construct($serviceId){

        $this->serviceId = $serviceId;
    }

    /**
     * Les en-têtes attendus par tous les endpoints sauf getToken.
     * @param string $accessToken
     * @return array
     */
    private function authHeaders($accessToken)
    {
        return array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
    }

    /**
     * Exécute un appel à l'API et retourne la réponse décodée.
     * @param string $method : GET, POST, DELETE
     * @param string $url : URL complète, query string comprise
     * @param string $accessToken
     * @param array|null $postFields : corps application/x-www-form-urlencoded, ou null
     * @return array
     */
    private function call($method, $url, $accessToken, $postFields = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->authHeaders($accessToken));

        if(is_array($postFields))
        {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        }

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $errno = curl_errno($curl);

        curl_close($curl);

        if($response === false || $errno !== 0)
        {
            //ATTENTION : sur sendCourrier et sendPublipostage, un échec réseau ne
            //signifie pas que le courrier n'est pas parti. L'issue est indéterminée.
            return array('success'=>false, 'error'=>array('code'=>'TRANSPORT_ERROR', 'text'=>$err));
        }

        $decoded = json_decode($response, true);

        if(!is_array($decoded))
        {
            return array('success'=>false, 'error'=>array('code'=>'INVALID_RESPONSE', 'text'=>substr((string) $response, 0, 200)));
        }

        return $decoded;
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
     * Demander un Access Token. Vous devez stocker ce token en local pour l'utiliser à chaque opération, et renouveler la demande lorsque le token est expiré ou pour ajouter de nouvelles IP.
     * @param string $secret : clé secrète, disponible dans votre compte Merci facteur Pro
     * @param array $ipArray : Tableau contenant les IP des serveurs à autoriser sur ce token array[$ip1, $ip2, $ip3, ...]
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Créer un nouvel utilisateur
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param array $arrayInfosUser : Tableau des informations de l'utilisateur ['email'=>$email,'firstName'=>$firstName,'lastName'=>$lastName] ; $email doit être unique.
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
    * Supprimer un utilisateur. Cela ne supprimer pas ses adresses, ni ses courriers qui sont conservés sur votre compte Merci facteur Pro
    * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
    * @param int $idUser : user ID de l'utilisateur à supprimer
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Récupérer le user ID à partir de l'adresse email. Pour utiliser cette fonction le moins possible, stockez les userId en local.
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idUser : user ID de l'utilisateur à modifier
     * @param array $arrayInfosUser : Tableau des informations de l'utilisateur ['email'=>$email,'firstName'=>$firstName,'lastName'=>$lastName] ; $email doit être unique.
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param array $zone : Zones geographiques à extraire : ['fr','om1','om2','z1','z2'] avec fr = France métropolitaine / OM1 = GUADELOUPE, GUYANE FRANCAISE, MARTINIQUE, MAYOTTE, REUNION, SAINT BARTHELEMY, SAINT MARTIN, ST-PIERRE-MIQUELON / OM2 = CLIPPERTON, NOUVELLE CALEDONIE, POLYNESIE FRANCAISE, TERRES AUSTRALES FR, WALLIS ET FUTUNA / Z1 : UE sauf France, Z2 : Reste du monde
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Créer une nouvelle adresse d'expéditeur ou de destinataire pour un utilisateur.
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idUser : user ID de l'utilisateur
     * @param string $type : Type d'adresse expediteur (=exp) ou type d'adresse destinataire (=dest)
     * @param array $arrayInfosAdress : Informations de l'adresse à créer ['type'=>'exp|dest','logo'=>'URL logo (uniquement pour exp)','civilite'=>'','nom'=>'','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'','ville'=>'','pays'=>''] / Sont obligatoires : (nom et/ou société), (cp), (ville), (pays) / pays doit être avec une orthographe conforme cf. getCountry() / Si une infos est inutilisée, la garder dans le tableau en string vide
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idAdress : id de l'adresse à modifier
     * @param array : Informations de l'adresse à modifier ['logo'=>'URL logo (uniquement pour exp)','civilite'=>'','nom'=>'','prenom'=>'','societe'=>'','adresse1'=>'','adresse2'=>'','adresse3'=>'','cp'=>'','ville'=>'','pays'=>''] / Sont obligatoires : (nom et/ou société), (cp), (ville), (pays) / pays doit être avec une orthographe conforme cf. getCountry() / Si une infos est inutilisée, la garder dans le tableau en string vide
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idUser : user ID de l'utilisateur dont on veux lister les adresses
     * @param string $type : exp ou dest suivant si vous souhaitez extraire les expéditeurs ou les destinataires.
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Valider l'envoi d'un courrier : ATTENTION, cette opération génère un courrier qui sera débité de votre compte, imprimé et posté. Si vous effectuez un test, merci de prendre contact avec le SAV de Merci facteur pour demander l'annulation de la commande.
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idUser : user ID de l'utilisateur qui envoi le courrier
     * @param array $adress : tableau contenant les id des adresse d'expéditeur et de destinataire(s) : ['exp'=>12,'dest'=>[23,25,94]]
     * @param array $infosLetter : null si pas d'envoi de lettre, ou tableau du/des url fichier(s) PDF à envoyer : 'files'=>['https://mysite/doc/file1.pdf', 'https://mysite/doc/file2.pdf'] ou 'base64files'=>['KHSFDKQDKQSD...', 'KHsdf45dfgdfgSFDKQDKQSD...']
     * @param array $infosPhoto : null si pas d'envoi de photo, ou tableau du/des url fichier(s) JPEG à envoyer : 'files'=>['https://mysite/img/file1.jpeg', 'https://mysite/img/file2.jpeg'] ou 'base64files'=>['KHSFDKQDKQSD...', 'KHsdf45dfgdfgSFDKQDKQSD...']
     * @param array $infosCard : null si pas d'envoi de carte, ou tableau contenant le format de la carte, l'url du visuel de la carte, et le html du texte de la carte : ['format'=>'postcard/naked-postcard/classic/folded/large', 'imgUrl'=>'https://mysite/doc/img.jpeg', 'imgBase64'=>'KHsdf45dfgdfgSFDKQDKQSD...', 'htmlText'=>'<div align="center">Bonjour !</div>']
     * @param string $modeEnvoi : Mode d'envoi normal|suivi|lrar|lrare|ere_otp_mail|ere_otp_sms
     * @param array $options : options facultatives (paramètre ajouté, les appels existants restent valides) :
     *      'print_sides' => 'recto'|'rectoverso'|'distinctrectoverso' (impression de la lettre)
     *      'final_filename' => libellé du fichier, 50 caractères max, sans extension
     *      'dateEnvoi' => 'AAAA-MM-JJ' complet et non passé (une valeur tronquée donne INVALID_DATE_ENVOI)
     *      'designation' => 50 caractères max ; visible par le destinataire sur un recommandé électronique
     *      'antidoublon' => référence STABLE identifiant le courrier, 200 caractères max. Une seconde tentative
     *                       avec la même valeur sur 30 jours glissants est refusée : c'est ce qui rend une
     *                       reprise après timeout possible sans créer de doublon.
     *      'gestionNpai' => 1 pour faire revenir et numériser les plis non distribués (modes normal et suivi)
     *      'anonymize' => ['delay'=>15, 'target'=>['content','exp','dest']]
     *      'enveloppe' => ['type'=>'template', 'value'=>'123456'] (enveloppe personnalisée, cf. « Votre branding »)
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "envoi_id"=>null|[int], "price"=>null|['total'=>['ht'=>float, 'ttc'=>float],'detail'=>['affranchissement'=>float]], "resume"=>['nb_dest'=>int nb destinataires, 'nb_page'=>int nb pages par courrier]] Il est conseillé de sauvegarder en local l'id des envois.
     */
    public function sendCourrier($accessToken, $idUser, $adress, $infosLetter, $infosCard, $infosPhoto, $modeEnvoi, $options = array())
    {
        if(!is_array($options))
        {return array('success'=>false,'error'=>'OPTIONS_MUST_BE_ARRAY');}

        if(!is_array($adress))
        {return array('success'=>false,'error'=>'ADRESS_MUST_BE_ARRAY');}
        
        if(!is_array($infosLetter) && !is_null($infosLetter))
        {return array('success'=>false,'error'=>'INFOS_LETTER_MUST_BE_NULL_OR_ARRAY');}
        
        if(!is_array($infosCard) && !is_null($infosCard))
        {return array('success'=>false,'error'=>'INFOS_CARD_MUST_BE_NULL_OR_ARRAY');}
        
        if(!is_array($infosPhoto) && !is_null($infosPhoto))
        {return array('success'=>false,'error'=>'INFOS_PHOTO_MUST_BE_NULL_OR_ARRAY');}
        
        if(is_null($infosLetter) && is_null($infosCard) && is_null($infosPhoto))
        {
            return array('success'=>false,'error'=>'NO_CONTENT');
        }
        
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $content = array();
        
        if(!is_null($infosLetter))
        {
            $content['letter']['files'] = $infosLetter['files'];
            $content['letter']['base64files'] = $infosLetter['base64files'];

            //print_sides et final_filename se placent DANS content.letter, pas à la racine.
            if(isset($options['print_sides']) && $options['print_sides']!='')
            {
                $content['letter']['print_sides'] = $options['print_sides'];
            }

            if(isset($options['final_filename']) && $options['final_filename']!='')
            {
                $content['letter']['final_filename'] = $options['final_filename'];
            }
        }
        else
        {
            $content['letter'] = '';
        }
        
        if(!is_null($infosPhoto))
        {
            $content['photo']['files'] = $infosPhoto['files'];
            $content['photo']['base64files'] = $infosPhoto['base64files'];
        }
        else
        {
            $content['photo'] = '';
        }
        
        if(!is_null($infosCard))
        {            
            $content['card']['format'] = $infosCard['format'];
            
            if(isset($infosCard['imgBase64']) && $infosCard['imgBase64']!='' && !is_null($infosCard['imgBase64']))
            {
                $content['card']['visuel']['type'] = 'base64';
                $content['card']['visuel']['value'] = $infosCard['imgBase64'];
            }
            elseif(isset($infosCard['imgUrl']) && $infosCard['imgUrl']!='' && !is_null($infosCard['imgUrl']))
            {
                $content['card']['visuel']['type'] = 'customimg';
                $content['card']['visuel']['value'] = $infosCard['imgUrl'];
            }
            else
            {
                return array('success'=>false,'error'=>'CARD_IMG_BAD_TYPE');
            }
            
            $content['card']['text']['type'] = 'html';
            $content['card']['text']['value'] = $infosCard['htmlText'];
        }
        else
        {
            $content['card'] = '';
        }
        
        $postFields = array(
            'idUser'=>$idUser,
            'adress'=> json_encode($adress),
            'content'=>json_encode($content),
            'modeEnvoi'=>$modeEnvoi
        );

        //dateEnvoi doit être complète, ou totalement absente : une valeur vide
        //ou tronquée déclenche INVALID_DATE_ENVOI.
        if(isset($options['dateEnvoi']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $options['dateEnvoi']))
        {
            $postFields['dateEnvoi'] = $options['dateEnvoi'];
        }

        if(isset($options['designation']) && $options['designation']!='')
        {
            $postFields['designation'] = substr($options['designation'], 0, 50);
        }

        if(isset($options['antidoublon']) && $options['antidoublon']!='')
        {
            $postFields['antidoublon'] = substr($options['antidoublon'], 0, 200);
        }

        if(isset($options['gestionNpai']) && $options['gestionNpai'])
        {
            $postFields['gestionNpai'] = 1;
        }

        if(isset($options['anonymize']) && is_array($options['anonymize']))
        {
            $postFields['anonymize'] = json_encode($options['anonymize']);
        }

        if(isset($options['enveloppe']) && is_array($options['enveloppe']))
        {
            $postFields['enveloppe'] = json_encode($options['enveloppe']);
        }

        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/sendCourrier', $accessToken, $postFields);
    }

    /**
     * Annuler un envoi. ATTENTION, opération irrémédiable.
     * Selon l'avancement des courriers, l'annulation peut être rejetée immédiatement, rejetée après un délai,
     * partielle (facturation partiellement annulée), ou intégrale. Un "success" ne garantit donc pas que le
     * courrier ne partira pas : vérifiez l'état réel avec getSuiviEnvoi() et les webhooks.
     * @param string $accessToken
     * @param int $idEnvoi : id retourné par sendCourrier()
     * @return array : ["success"=>false|true, "error"=>null|code_erreur]
     */
    public function deleteEnvoi($accessToken, $idEnvoi)
    {
        return $this->call('DELETE', 'https://www.merci-facteur.com/api/1.2/prod/service/deleteEnvoi?idEnvoi='.urlencode($idEnvoi), $accessToken);
    }

    /**
     * Récupérer une preuve (preuve de dépôt, avis de réception, preuve de téléchargement).
     * Les webhooks "pdd" et "are" transportent déjà ces documents en base64 : cet appel sert à la preuve de
     * téléchargement d'un recommandé électronique, au rattrapage d'un webhook manqué, et à l'historique.
     * @param string $accessToken
     * @param string $trackingNumber : numéro de suivi La Poste (ex. 2C123456789), présent dans les webhooks
     *                                 à partir de l'événement "printed". Ce n'est ni l'idEnvoi, ni la ref_courrier.
     * @param string $document : depot|reception|telechargement
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "format_return"=>"pdf"|"jpeg", "document"=>base64]
     */
    public function getProof($accessToken, $trackingNumber, $document)
    {
        $url = 'https://www.merci-facteur.com/api/1.2/prod/service/getProof?trackingNumber='.urlencode($trackingNumber).'&document='.urlencode($document);

        return $this->call('GET', $url, $accessToken);
    }

    /**
     * Obtenir l'URL signée (valable 5 minutes) du fichier final d'un courrier.
     * @param string $accessToken
     * @param string $referenceCourrier : référence Merci Facteur, de la forme "1234-5678"
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "result"=>[["url"=>string, "refCourrier"=>string, "expire"=>timestamp]]]
     */
    public function getLetterFinalFile($accessToken, $referenceCourrier)
    {
        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/getLetterFinalFile?referenceCourrier='.urlencode($referenceCourrier), $accessToken);
    }

    /**
     * Supprimer une adresse du carnet d'adresses.
     * @param string $accessToken
     * @param int $idAdress
     * @return array : ["success"=>false|true, "error"=>null|code_erreur]
     */
    public function deleteAdress($accessToken, $idAdress)
    {
        return $this->call('DELETE', 'https://www.merci-facteur.com/api/1.2/prod/service/deleteAdress?idAdress='.urlencode($idAdress), $accessToken);
    }

    /**
     * Obtenir le détail d'adresses à partir de leurs ID (50 maximum par appel).
     * @param string $accessToken
     * @param array $idAdressArray : [123, 456, 789]
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "adress"=>[idAdresse=>[infos]]]
     */
    public function getAdressInfos($accessToken, $idAdressArray)
    {
        if(!is_array($idAdressArray))
        {return array('success'=>false,'error'=>'ID_ADRESS_MUST_BE_ARRAY');}

        if(count($idAdressArray) > 50)
        {return array('success'=>false,'error'=>'TOO_MANY_ADRESS');}

        $query = array();
        foreach($idAdressArray as $id)
        {
            $query[] = 'idAdress[]='.urlencode($id);
        }

        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/getAdressInfos?'.implode('&', $query), $accessToken);
    }

    /**
     * Calculer le prix d'un envoi avant de l'envoyer.
     * @param string $accessToken
     * @param string $modeEnvoi : normal|suivi|lrar|lrare|ere_otp_mail
     * @param array $params : ['paysDestinataire'=>['FRANCE'], 'idDestinataire'=>[123], 'letterPageNumber'=>2,
     *                         'photoNumber'=>0, 'letterPrintSides'=>'recto', 'cardFormat'=>'', 'cardPapier'=>'', 'cardCoin'=>'']
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "poids"=>string, "affranchissement"=>[...], "content"=>[...]]
     */
    public function getPostagePrice($accessToken, $modeEnvoi, $params = array())
    {
        if(!is_array($params))
        {return array('success'=>false,'error'=>'PARAMS_MUST_BE_ARRAY');}

        $query = array('modeEnvoi[]='.urlencode($modeEnvoi));

        foreach(array('paysDestinataire','idDestinataire') as $cle)
        {
            if(isset($params[$cle]) && is_array($params[$cle]))
            {
                foreach($params[$cle] as $valeur)
                {
                    $query[] = $cle.'[]='.urlencode($valeur);
                }
            }
        }

        $query[] = 'letterPageNumber='.urlencode(isset($params['letterPageNumber']) ? $params['letterPageNumber'] : 0);
        $query[] = 'photoNumber='.urlencode(isset($params['photoNumber']) ? $params['photoNumber'] : 0);
        $query[] = 'cardFormat[]='.urlencode(isset($params['cardFormat']) ? $params['cardFormat'] : '');
        $query[] = 'cardPapier[]='.urlencode(isset($params['cardPapier']) ? $params['cardPapier'] : '');
        $query[] = 'cardCoin[]='.urlencode(isset($params['cardCoin']) ? $params['cardCoin'] : '');

        if(isset($params['letterPrintSides']) && $params['letterPrintSides']!='')
        {
            $query[] = 'letterPrintSides='.urlencode($params['letterPrintSides']);
        }

        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/getPostagePrice?'.implode('&', $query), $accessToken);
    }

    /**
     * Publipostage, phase 1 : envoi du template docx.
     * Contrôlez le templateValidation retourné (nbPage, inputs) avant la phase 2, puis transmettez-le sans le modifier.
     * @param string $accessToken
     * @param string $template : URL du fichier docx, ou son contenu encodé en base64
     * @param string $typeTemplate : file|base64
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "templateValidation"=>[...]]
     */
    public function templatePublipostage($accessToken, $template, $typeTemplate = 'file')
    {
        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/templatePublipostage', $accessToken, array(
            'typeTemplate'=>$typeTemplate,
            'template'=>$template
        ));
    }

    /**
     * Publipostage, phase 2 : envoi de la source de données.
     * @param string $accessToken
     * @param int $idUser
     * @param array $templateValidation : le tableau retourné par templatePublipostage(), inchangé
     * @param string $typeSource : file|base64|json
     * @param string|array $source : URL du CSV/TXT, base64, ou tableau de destinataires
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "idEnvoi"=>int, "sourceValidation"=>[...]]
     */
    public function sourcePublipostage($accessToken, $idUser, $templateValidation, $typeSource, $source)
    {
        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/sourcePublipostage', $accessToken, array(
            'idUser'=>$idUser,
            'templateValidation'=>json_encode($templateValidation),
            'source'=>json_encode(array('type'=>$typeSource, 'value'=>$source))
        ));
    }

    /**
     * Publipostage, phase 3 : validation. ATTENTION, cette phase déclenche la fusion, l'impression et l'envoi
     * de l'ensemble des lettres, qui sont facturées.
     * @param string $accessToken
     * @param int $idEnvoi : identifiant retourné par sourcePublipostage()
     * @param int|array $exp : id de l'adresse d'expéditeur, ou tableau contenant directement l'adresse
     * @param string $modeEnvoi : normal|suivi|lrar|lrare
     * @param array $options : ['anonymize'=>['delay'=>15,'target'=>['content','exp','dest']]]
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "envoi_id"=>[int], "price"=>[...], "resume"=>[...]]
     */
    public function sendPublipostage($accessToken, $idEnvoi, $exp, $modeEnvoi, $options = array())
    {
        $postFields = array('idEnvoi'=>$idEnvoi, 'modeEnvoi'=>$modeEnvoi);

        if(is_array($exp))
        {
            $postFields['jsonExp'] = json_encode($exp);
        }
        else
        {
            $postFields['idExp'] = $exp;
        }

        if(isset($options['anonymize']) && is_array($options['anonymize']))
        {
            $postFields['anonymize'] = json_encode($options['anonymize']);
        }

        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/sendPublipostage', $accessToken, $postFields);
    }

    /**
     * Ouvrir un ticket SAV au sujet d'un courrier.
     * @param string $accessToken
     * @param array $infos : ['yourServiceName'=>'Monsite.com', 'email'=>'client@exemple.fr', 'sujet'=>'...', 'messageTexte'=>'...', 'referenceCourrier'=>'1234-5678']
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "sav_id"=>string, "sav_token"=>string]
     */
    public function openSavTicket($accessToken, $infos)
    {
        if(!is_array($infos))
        {return array('success'=>false,'error'=>'INFOS_MUST_BE_ARRAY');}

        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/openSavTicket', $accessToken, $infos);
    }

    /**
     * Obtenir le plan, le crédit et le quota de pages du compte.
     * @param string $accessToken
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "quotas"=>["plan"=>[...], "credit"=>[...], "pages"=>[...]]]
     */
    public function getQuotaCompte($accessToken)
    {
        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/getQuotaCompte', $accessToken);
    }

    /**
     * Définir l'URL de réception des webhooks. Une chaîne vide supprime l'URL.
     * @param string $accessToken
     * @param string $url
     * @return array : ["success"=>false|true, "error"=>null|code_erreur]
     */
    public function setWebhookEndpoint($accessToken, $url)
    {
        return $this->call('POST', 'https://www.merci-facteur.com/api/1.2/prod/service/setWebhookEndpoint', $accessToken, array('url'=>$url));
    }

    /**
     * Lire l'URL de webhook configurée sur le compte.
     * @param string $accessToken
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "url"=>string]
     */
    public function getWebhookEndpoint($accessToken)
    {
        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/getWebhookEndpoint', $accessToken);
    }

    /**
     * Lister les codes d'erreur de l'API et leur signification.
     * Les codes ne changent jamais, contrairement aux messages : construisez votre logique sur le code.
     * @param string $accessToken
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, "listErrors"=>[code=>signification]]
     */
    public function listErrors($accessToken)
    {
        return $this->call('GET', 'https://www.merci-facteur.com/api/1.2/prod/service/listErrors', $accessToken);
    }

    /**
     * Vérifier qu'un webhook vient bien de Merci Facteur.
     * Les webhooks partent de plusieurs IP : le filtrage par IP ne fonctionne pas. La clé attendue se trouve
     * dans l'onglet "API" de votre compte, et arrive dans l'en-tête X-Mf-Webhook-Secret-Key.
     * @param string $webhookSecretKey : votre clé
     * @param string $receivedKey : $_SERVER['HTTP_X_MF_WEBHOOK_SECRET_KEY']
     * @return bool
     */
    public function checkWebhookSecretKey($webhookSecretKey, $receivedKey)
    {
        if(!is_string($webhookSecretKey) || !is_string($receivedKey) || $webhookSecretKey === '' || $receivedKey === '')
        {return false;}

        return hash_equals($webhookSecretKey, $receivedKey);
    }

    /**
     * Lister les 50 derniers envois d'un utilisateur
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Obtenir le détail d'un envoi en particulier (un envoi peut être composé de plusieurs destinataires et donc de plusieurs courriers
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
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
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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
     * Obtenir le suivi d'un envoi en particulier (un envoi peut être composé de plusieurs destinataires et donc de plusieurs courriers
     * @param string $accessToken : Access Token que vous avez demandé avec getAccessToken() ou que vous avez stocké en local
     * @param int $idEnvoi : id de l'envoi en question
     * @return array : ["success"=>false|true, "error"=>null|code_erreur, statutPrintEnvoi =>null|"etat de l'impression", "suiviCourrier"=>detail du suivi]
     */
    public function getSuiviEnvoi($accessToken, $idEnvoi)
    {
        $headers = array(
            'ww-access-token:' . $accessToken,
            'ww-service-id:' . $this->serviceId
        );
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://www.merci-facteur.com/api/1.2/prod/service/getSuiviEnvoi?idEnvoi='.$idEnvoi);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
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

