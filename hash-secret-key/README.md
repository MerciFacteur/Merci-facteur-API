# Algorithme de hashage de la secret key

Votre secret key ne doit jamais être divulguée, ni transiter en clair.

Vous n'avez besoin d'utiliser la secret key que pour demander l'access token qui vous permettra de faires toutes les autres opérations.

Pour demander l'access token, vous devez envoyer votre secret key hashée (et non en clair). Ce hashage sera valide durant 5 minutes uniquement.

### Exemple de hashage de la secret key (PHP) :
```php
function hashSecretKey($secretKey, $serviceId){
        $ts = time();
        $hashed = hash_hmac('sha256', $serviceId.$ts, $secretKey, false);
        
        return array('timestamp'=>$ts, 'hash'=>$hashed);
    }
 ```
