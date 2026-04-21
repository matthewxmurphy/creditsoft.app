#!/bin/sh

cd "/Users/mmurphy/Code/CreditSoft" || exit 1
php \
  -d opcache.enable_cli=1 \
  -d opcache.memory_consumption=256 \
  -d opcache.interned_strings_buffer=32 \
  -d opcache.max_accelerated_files=50000 \
  -d opcache.validate_timestamps=1 \
  -d opcache.revalidate_freq=0 \
  -d opcache.file_update_protection=0 \
  -d realpath_cache_size=64M \
  -d realpath_cache_ttl=600 \
  -S 127.0.0.1:8001 -t public

# If the app shell comes back blank after a system restart or code update,
# run this once before starting the server again:
# php artisan optimize:clear && php artisan optimize
