#!/bin/bash
# Generate self-signed SSL certificate for development

# Create certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/key.pem \
    -out /etc/nginx/ssl/cert.pem \
    -subj "/C=ID/ST=East Java/L=Madiun/O=Kabupaten Madiun/OU=IT Department/CN=pendataan.local"

echo "SSL certificate generated successfully!"
echo "Add 'pendataan.local' to your /etc/hosts file:"
echo "127.0.0.1 pendataan.local"
