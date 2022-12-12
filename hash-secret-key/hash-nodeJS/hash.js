import crypto from 'crypto';
function hashSecretKey(secretKey, serviceId) {
    const timestamp = Math.floor(Date.now() / 1000);
    const hash = crypto.createHmac('sha256', secretKey).update(serviceId + timestamp).digest('hex');
    return {
        timestamp,
        hash
    };
}
