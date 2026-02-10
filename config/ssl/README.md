# Azure MySQL SSL Certificate

This directory should contain the DigiCert Global Root CA certificate for secure connections to Azure Database for MySQL.

## Download Certificate

Download the certificate from:
https://dl.cacerts.digicert.com/DigiCertGlobalRootCA.crt.pem

Save it as `DigiCertGlobalRootCA.crt.pem` in this directory.

## Why is this needed?

Azure Database for MySQL enforces SSL connections by default. The certificate is used to verify the server's identity during the TLS handshake.

## Alternative: Disable SSL Verification (Not Recommended)

For development only, you can disable SSL verification by setting:
```
AZURE_MYSQL_SSL_VERIFY=false
```

**Warning:** This is insecure and should never be used in production.
