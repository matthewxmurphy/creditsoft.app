ARG CRM_BASE_IMAGE=update.creditsoft.app/creditsoft/crm-sidecar:latest
FROM ${CRM_BASE_IMAGE}

USER root

COPY public/favicon.ico /tmp/creditsoft-crm-favicon.ico
COPY public/favicon.svg /tmp/creditsoft-crm-favicon.svg
COPY public/apple-touch-icon.png /tmp/creditsoft-crm-icon.png
COPY public/pwa-192.png /tmp/creditsoft-crm-icon-192.png
COPY public/pwa-512.png /tmp/creditsoft-crm-icon-512.png
COPY docker/crm-sidecar/apply-creditsoft-branding.sh /usr/local/bin/apply-creditsoft-branding

RUN chmod +x /usr/local/bin/apply-creditsoft-branding \
    && /usr/local/bin/apply-creditsoft-branding
