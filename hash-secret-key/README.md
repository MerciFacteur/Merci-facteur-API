# Algorithme de hashage de la secret key

Votre secret key ne doit jamais être divulguée, ni transiter en clair.

Vous n'avez besoin d'utiliser la secret key que pour demander l'access token qui vous permettra de faires toutes les autres opérations.

Pour demander l'access token, vous devez envoyer votre secret key hashée (et non en clair). Ce hashage sera valide durant 5 minutes uniquement.

Vous disposez, dans votre interface Merci facteur PRO d'un bouton pour générer un hashage de votre secret key à des fins de tests.

### Exemple de hashage de la secret key (PHP) :
```php
function hashSecretKey($secretKey, $serviceId){
        $ts = time();
        $hashed = hash_hmac('sha256', $serviceId.$ts, $secretKey, false);
        
        return array('timestamp'=>$ts, 'hash'=>$hashed);
    }
 ```
 
 ### Exemple de hashage de la secret key (nodeJS) :
```javascript
function hashSecretKey(secretKey, serviceId) {
    const timestamp = Math.floor(Date.now() / 1000);
    const hash = crypto.createHmac('sha256', secretKey).update(serviceId + timestamp).digest('hex');
    return {
        timestamp,
        hash
    };
}
 ```
