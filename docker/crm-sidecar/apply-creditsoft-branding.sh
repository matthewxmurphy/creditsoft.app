#!/usr/bin/env sh
set -eu

front_dir="/app/packages/twenty-server/dist/front"
assets_dir="${front_dir}/assets"
icons_dir="${front_dir}/images/icons"
launcher_icon="${icons_dir}/android/android-launchericon-192-192.png"

if [ -d "${front_dir}" ]; then
  find "${front_dir}" -type f \( -name '*.html' -o -name '*.js' -o -name '*.json' \) -exec sed -i \
    -e 's#https://twenty.com/legal/privacy#https://www.creditsoft.app/privacy#g' \
    -e 's#https://twenty.com/privacy#https://www.creditsoft.app/privacy#g' \
    -e 's#https://twenty.com/legal/terms#https://www.creditsoft.app/terms#g' \
    -e 's#https://twenty.com/terms#https://www.creditsoft.app/terms#g' \
    -e 's#https://docs.twenty.com/user-guide/introduction#https://www.creditsoft.app/resources#g' \
    -e 's#https://docs.twenty.com#https://www.creditsoft.app/resources\##g' \
    -e 's#https://www.creditsoft.app/resources/user-guide/introduction#https://www.creditsoft.app/resources#g' \
    -e 's#https://twenty.com/releases#https://www.creditsoft.app/resources#g' \
    -e 's#https://twenty.com/developers/section/self-hosting/self-hosting-var\#ai-features#https://www.creditsoft.app/resources#g' \
    -e 's#https://twentyhq.github.io/placeholder-images/workspaces/twenty-logo.png#/images/icons/android/android-launchericon-192-192.png#g' \
    -e 's#type="image/x-icon"#type="image/png"#g' \
    -e 's#<title>Twenty</title>#<title>CreditSoft CRM</title>#g' \
    -e 's#content="Twenty"#content="CreditSoft CRM"#g' \
    -e 's#content="A modern open-source CRM"#content="CreditSoft CRM for clients, leads, affiliates, and office follow-up"#g' \
    -e 's#"name": "Twenty"#"name": "CreditSoft CRM"#g' \
    -e 's#"short_name": "Twenty"#"short_name": "CreditSoft"#g' \
    -e 's#Welcome to Twenty#Welcome to CreditSoft CRM#g' \
    -e 's#By using Twenty, you agree to the#By using CreditSoft CRM, you agree to the#g' \
    -e 's#Twenty team#CreditSoft team#g' \
    {} +
fi

if [ -f /tmp/creditsoft-crm-icon.png ] && [ -f "${launcher_icon}" ]; then
  cp /tmp/creditsoft-crm-icon.png "${launcher_icon}"
fi

if [ -d "${icons_dir}" ]; then
  if [ -f /tmp/creditsoft-crm-icon-192.png ]; then
    find "${icons_dir}" -type f -name '*.png' -exec cp /tmp/creditsoft-crm-icon-192.png {} \;
  fi

  if [ -f /tmp/creditsoft-crm-icon-512.png ]; then
    find "${icons_dir}" -type f \( -name '*512*.png' -o -name '*1024*.png' \) -exec cp /tmp/creditsoft-crm-icon-512.png {} \;
  fi
fi

if [ -f /tmp/creditsoft-crm-favicon.ico ]; then
  cp /tmp/creditsoft-crm-favicon.ico "${front_dir}/favicon.ico"
fi

if [ -f /tmp/creditsoft-crm-favicon.svg ]; then
  cp /tmp/creditsoft-crm-favicon.svg "${front_dir}/favicon.svg"
fi
