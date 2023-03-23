#!/usr/bin/env bash

VERSION=1.2

cd "$(dirname "$0")"

cd ../../ #AppBundle directory
APPBUNDLE_BASE=$(pwd)

cd ../../

BASE=$(pwd)

cd $APPBUNDLE_BASE

FULLPATH="$( cd -- "$(dirname "$0")" >/dev/null 2>&1 ; pwd -P )"

if [[ $1 = "fix-permissions" ]]; then
  chmod +x Resources/scripts/fix_permissions.sh && Resources/scripts/fix_permissions.sh
fi

if [[ $FULLPATH == *"home/shipshape/www/"* ]]; then
  printf "\nRunning fix premissions..."
  chmod +x Resources/scripts/fix_permissions.sh && Resources/scripts/fix_permissions.sh
fi

### GET CURRENT VERSION
CURRENT_VERSION=0
IS_PRODUCTION=0
cd $BASE
if [ -f .env ]
then
  CORE_VERSION_TMP=$(grep "^CORE_VERSION=" .env | cut "-d " -f2-)
  CORE_VERSION_PARTS=(${CORE_VERSION_TMP//=/ })
  if [ "${CORE_VERSION_PARTS[0]}" = "CORE_VERSION" ]
  then
    CURRENT_VERSION=${CORE_VERSION_PARTS[1]}
  fi

  IS_PRODUCTION_TMP=$(grep "^IS_PRODUCTION=" .env | cut "-d " -f2-)
  IS_PRODUCTION_PARTS=(${IS_PRODUCTION_TMP//=/ })
  if [ "${IS_PRODUCTION_PARTS[0]}" = "IS_PRODUCTION" ]
  then
    IS_PRODUCTION=${IS_PRODUCTION_PARTS[1]}
  fi
fi

cd $APPBUNDLE_BASE

if [ $CURRENT_VERSION = 0 ]
then

  if test -f "update.php"; then
      printf "\nRunning database schema update script..."
      php update.php
  fi

  if test -f "change_collation.php"; then
      printf "\nRunning database change_collation script..."
      php change_collation.php
  fi

  cd $BASE

  php -q bin/console update:helper validate_installation
  if [ $? -eq 0 ]; then exit; fi

  php bin/console update:helper validate_env _with_default

  php bin/console update:helper update_active_on_blog_category_s_page
  php bin/console update:helper fix_s_product_configuration_product_group_link_entity
  php bin/console update:helper update_reset_password_form

  #Add new cron jobs
  php bin/console cron:run add_update_cron_job 'Unlock quotes' '* * * * *' 'Unlock quotes' 'crmhelper:run type:unlock_quotes' 1 2
  php bin/console cron:run add_update_cron_job 'Automatically fix routes' '*/55 * * * *' 'Automatically fix routes' 'scommercehelper:function type:automatically_fix_rutes' 1 10
  php bin/console cron:run add_update_cron_job 'Automatically GDPR decline' '0 5 * * *' 'Automatically GDPR decline' 'crmhelper:run type:automatic_gdpr_decline' 1 2
  #php bin/console cron:run add_update_cron_job 'Send product remind emails' '* * * * *' 'Sends product remind emails every hour' 'automationsEmail:run function:remind_me' 1 1
  php bin/console cron:run add_update_cron_job 'Import local images' '*/5 * * * *' 'Runs images import every 5 minutes' 'crmhelper:run type:import_files arg1:product_images arg2:product arg3:code' 1 1

  php bin/console user:helper remove_user_by_username testnibrod
  php bin/console user:helper remove_user_by_username ksusac
  php bin/console user:helper remove_user_by_username partner
  php bin/console user:helper remove_user_by_username petar
  #IGOR
  php bin/console user:helper add_admin_user igor "0918843391" ROLE_ADMIN igor@shipshape-solutions.com Igor Draušnik "1,10"
  #DEA
  php bin/console user:helper add_admin_user dea "22022022" ROLE_ADMIN dea@shipshape-solutions.com Dea Marušić "1,10"
  #DAVOR
  php bin/console user:helper add_admin_user davor "57744292" ROLE_ADMIN davor@shipshape-solutions.com Davor Španić "1,10"
  #ALEN
  php bin/console user:helper add_admin_user alen "57744292" ROLE_ADMIN alen.pagac@gmail.com Alen Pagač "1,10"
  #HRVOJE
  php bin/console user:helper add_admin_user hrvoje '0995117889$hrc' ROLE_ADMIN hrvoje.rukavina@shipshape-solutions.com Hrvoje Rukavina "1,10"
  #VALENTINO
  php bin/console user:helper add_admin_user valentino "Excel_22" ROLE_ADMIN valentino@shipshape-solutions.com Valentino Mrazović "1,10"
  #ERNEST
  php bin/console user:helper add_admin_user ernest "12111211" ROLE_ADMIN ernest@shipshape-solutions.com Ernest Antolović "1,10"
  #IVAN
  php bin/console user:helper add_admin_user ivan "Zelen54!" ROLE_ADMIN ivan@shipshape-solutions.com Ivan Vidović "1,10"
  #VIKI
  php bin/console user:helper add_admin_user vjurkovic "0917943609" ROLE_ADMIN viktorija@shipshape-solutions.com Viktorija Jurković "1,10"

  #Other
  php bin/console update:helper attribute_to_json product_entity description_title
  php bin/console update:helper attribute_to_json product_entity specs_title
  php bin/console update:helper attribute_to_json product_entity video_title

  php bin/console update:helper remove_custom_files page 765aa15e5471684099130084ea75197e
  php bin/console update:helper remove_custom_files page 101bc9ae307ee8b7b9db6147de2cb3b9
  php bin/console update:helper remove_custom_files page_block a2d5b475d2ded24dbc09a7ef31e5562f
  php bin/console update:helper remove_custom_files attribute_group 5d05bddba07fe02fb9f5ff1edc563d6e
  php bin/console update:helper remove_custom_files attribute_group 4f34a262d369e07582304a0cb1a13a63
  php bin/console update:helper remove_custom_files attribute_group 603634de914799.29567688
  php bin/console update:helper remove_custom_files list_view 8a90a6a90ec3ba6182350403bc5696fe
  php bin/console update:helper remove_custom_files list_view 22f88f336181d898e66ecc978f7a5e2d
  php bin/console update:helper remove_custom_files list_view b22a03099a4511106733f08173c9061f
  php bin/console update:helper remove_custom_files list_view 8ce9953598a193355858043aef3d8284
  php bin/console update:helper remove_custom_files navigation_link 603cfa7c1cbc84.76199163
  php bin/console update:helper remove_custom_files attribute 6048b4299800f2.22630157

  #DELETE INT ALSO ATTRIBUTE OPTIONS
  php bin/console update:helper delete_entity_type entity_type 604883143b9ff2.62367708
  php bin/console update:helper delete_entity_type entity_type 605376b8954906.00178359
  php bin/console update:helper delete_entity_type entity_type 6059cac1a26e53.85921217
  php bin/console update:helper delete_entity_type entity_type 604cd619b7e476.06809947

  #DELETE owner_erp_id on account
  php bin/console update:helper delete_attribute attribute c3545b61f5690f89127e4313dac169b5

  #DELETE account_meetings activity
  php bin/console update:helper delete_listview list_view 15014e825ee094fefe69d854f4f51b51
  #DELETE account_calls activity
  php bin/console update:helper delete_listview list_view 38fc1d8cf348bcca6f28cb07da878753

  #DELETE generate_email
  php bin/console update:helper delete_attribute attribute f9c6f3c14c51c5573944d873985e88e5
  #DELETE email_emplate
  php bin/console update:helper delete_attribute attribute 14f1a7dfc324cf48bf3dde05a5fc2aa3
  #DELETE notification_email_entity
  php bin/console update:helper delete_entity_type entity_type 50e653a5ed9bc509c84b0eb809f079a2
  #DELETE page block email_entity from notification
  php bin/console update:helper delete_page_block page_block be54a26380e99c5cc20a4e643d6e2d31
  #DELETE notification_type_employee_link_entity
  php bin/console update:helper delete_entity_type entity_type e2883496a1f3ad954236e287910541f5


  #DELETE BON.HR
  php bin/console update:helper delete_entity_type entity_type b8ecf9bf89af60efe66968f89a5f8632

  ### activity_entity

  php bin/console update:helper update_activity_attributes
  # call_type_id
  php bin/console update:helper delete_attribute attribute 4bee229d77591911c0a012661ddf217f
  # call_purpose_id
  php bin/console update:helper delete_attribute attribute 6b7545b227291ce6f28827346ac12085
  # time_start
  php bin/console update:helper delete_attribute attribute 616b6938b2369cd7dc1c30008e5a93df
  # time_end
  php bin/console update:helper delete_attribute attribute d8b5eb2cd2aa5c4c6e7a6ff9b5d7ccad
  # billable
  php bin/console update:helper delete_attribute attribute 16f177f859b022e4912184b3ec341b54
  # project_id
  php bin/console update:helper delete_attribute attribute d2435e76d12ff93553546bfa074d6eb7
  # account_id
  php bin/console update:helper delete_attribute attribute 411c03eb8c3d53133c7bc414b75fc0fc
  # contact_id
  php bin/console update:helper delete_attribute attribute f22f38417cf75d6251bb903bb49c358a
  # group_id
  php bin/console update:helper delete_attribute attribute f629543891f42ebbe1b09ffb52193cd9
  # contacts
  php bin/console update:helper delete_attribute attribute dad701a35f07feb94b21de224a7b0083
  # is running
  php bin/console update:helper delete_attribute attribute 06501b796998ec4b809b47b241f91cc2
  # delete entity activity_contact_link
  php bin/console update:helper delete_entity_type entity_type 70d35c18d7bc5e17670e9eab66838f3a

  php bin/console update:helper delete_attribute attribute 60b5ff66c64f65.58053172
  php bin/console update:helper delete_attribute attribute 60b5fe8426c2e8.85288585
  php bin/console update:helper delete_attribute attribute 60b5ff859a1122.85368496
  php bin/console update:helper delete_attribute attribute 60d9cf8bf1bbe0.48689446
  php bin/console update:helper delete_attribute attribute 68bf15e536d0f4d56e34fff8f1931b05
  php bin/console update:helper delete_attribute attribute 62011912698798.85004399
  php bin/console update:helper delete_attribute attribute 620148c9197db8.25728350


  ##REMOVE UNUSED/OLD BUNDLES
  php bin/console update:helper remove_bon_hr_business_bundle
  php bin/console update:helper google_images_business_bundle
  php bin/console update:helper remove_sendinblue_business_bundle
  php bin/console update:helper data_transfer_business_bundle
  php bin/console update:helper remove_settings_from_app_base
  php bin/console update:helper remove_old_update_php
  php bin/console update:helper remove_asset_bundle

  composer rebuild-entities all

  php bin/console update:helper update_delivery_payment_options
  php bin/console update:helper update_discount_coupons
  php bin/console update:helper fix_id_data_type

  CURRENT_VERSION=1.0

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi

if [ $CURRENT_VERSION = 1.0 ]
then

  cd $BASE

  composer rebuild-entities all
  php bin/console db_update:helper rebuild_fk

  php -q bin/console update:helper validate_installation
  if [ $? -eq 0 ]; then exit; fi

  CURRENT_VERSION=1.1

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi

if [ $CURRENT_VERSION = 1.1 ]
then

  cd $BASE

  composer rebuild-entities CrmBusinessBundle

  php bin/console update:helper update_contact_on_quote_and_order

  RED='\033[0;31m'
  NC='\033[0m' # No Color
  printf "I ${RED}PLEASE CHECK CUSTOM crmProcessManager AND REMOVE orderCustomer and orderInstallment!!!${NC}\n"

  CURRENT_VERSION=1.2

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.2 ]
then

  cd $BASE

  php bin/console update:helper product_account_price_set_uq

  CURRENT_VERSION=1.3

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.3 ]
then

  cd $BASE

  composer rebuild-entities all

  php bin/console update:helper update_s_front_block_show_on_store
  php bin/console update:helper delete_entity_type entity_type 15ccd8eeaef0124dd293c1fcdbd1f4d4
  php bin/console update:helper update_missing_uid

  CURRENT_VERSION=1.4

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.4 ]
then

  cd $BASE

  ##CLEAN .env files
  php bin/console update:helper transfer_from_env_to_settings FILTERS_SALEABLE
  php bin/console update:helper transfer_from_env_to_settings FILTERS_IS_ON_DISCOUNT
  php bin/console update:helper transfer_from_env_to_settings FILTERS_PRICE
  php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES
  php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_LEVEL
  php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_LEVEL_SEARCH
  php bin/console update:helper transfer_from_env_to_settings FILTERS_CATEGORIES_SHOW_NEXT_CHILDREN_ONLY
  php bin/console update:helper transfer_from_env_to_settings FILTERS_ONLY_IMAGES


  ##JUST REMOVE
  php bin/console update:helper remove_from_env ORDER_RETURN_DEFAULT_PARCEL_SOURCE
  php bin/console update:helper remove_from_env STORE_NOTIFICATION_OF_PRODUCT_INQUITY
  php bin/console update:helper remove_from_env SENDINBLUE_KEY


  CURRENT_VERSION=1.5

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.5 ]
then

  cd $BASE

  #DELETE NAVIGATION LINK SETTINGS
  php bin/console update:helper delete_navigation_link 617fe43ebc6472.97324866

  #REMOVE DATA TRANSFER BUSINESS BUNDLE
  php bin/console update:helper data_transfer_business_bundle

  #SECURITY!!!! DA BACKEND RUTE BUDU DOSTUPNE SAMO BACKENDU
  php bin/console update:helper update_routing_yml

  CURRENT_VERSION=1.6

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.6 ]
then

  cd $BASE

  #REMOVE DATA TRANSFER BUSINESS BUNDLE
  #php bin/console update:helper data_transfer_business_bundle
  #composer remove shipshapesolutions/datatransferbusinessbundle

  #REMOVE DATA SHAPEBEHAT
  #php bin/console update:helper remove_shapebehat
  #composer remove shipshapesolutions/shapebehat

  CURRENT_VERSION=1.7

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.7 ]
then

  cd $BASE

  php bin/console update:helper update_1_8

  CURRENT_VERSION=1.8

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.8 ]
then

  cd $BASE

  php bin/console update:helper update_1_9
  php bin/console cron:run add_update_cron_job 'Generate facebook export' '35 3 * * *' '' 'scommercehelper:function generate_facebook_export 3' 0 10

  CURRENT_VERSION=1.9

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 1.9 ]
then

  cd $BASE

  php bin/console update:helper update_2_1

  CURRENT_VERSION=2.1

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.1 ]
then

  cd $BASE

  php bin/console db_update:helper rebuild_fk

  CURRENT_VERSION=2.2

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.2 ]
then

  cd $BASE

  php bin/console update:helper update_2_3

  CURRENT_VERSION=2.3

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.3 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities AppBundle
  php bin/console update:helper update_2_6
  php bin/console admin:entity transfer_all_mail_to_sent_emails

  CURRENT_VERSION=2.4

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.4 ]
then

  cd $BASE

  php bin/console update:helper update_2_5
  mv var/cache var/_cache
  rm -rf var/_cache
  if [ $IS_PRODUCTION = 0 ]
  then
    /usr/local/bin/php -d memory_limit=-1 /opt/cpanel/composer/bin/composer remove sentry/sentry-symfony sentry/sentry
  fi

  CURRENT_VERSION=2.5

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.5 ]
then

  cd $BASE

  php bin/console update:helper update_2_6
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 6291ff34a7f0f9.65329474
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 6 629200cba71cb9.96857518
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 1 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 2 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 3 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 4 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 5 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 6 fba4d20f6ce62a8135eeac816b22ff9c
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 62488a5d4246d8.44413530
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 7 61f941974d2034.83352780
  php bin/console user:helper add_privileges_to_object_and_group ROLE_COMMERCE_ADMIN 5 1b3329290bffea8cc4b871d66d173a67

  CURRENT_VERSION=2.6

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.6 ]
then

  cd $BASE

  php bin/console update:helper update_2_7

  CURRENT_VERSION=2.7

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.7 ]
then

  cd $BASE

  php bin/console update:helper update_2_8

  CURRENT_VERSION=2.8

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.8 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities AppBundle
  composer rebuild-entities CrmBusinessBundle
  php bin/console update:helper update_2_9

  CURRENT_VERSION=2.9

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.9 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities AppBundle
  composer rebuild-entities CrmBusinessBundle
  php bin/console update:helper update_2_9

  CURRENT_VERSION=2.91

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 2.91 ]
then

  cd $BASE

  php bin/console update:helper update_3_2

  CURRENT_VERSION=3.2

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.2 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities PaymentProvidersBusinessBundle
  php bin/console update:helper update_3_3

  CURRENT_VERSION=3.3

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.3 ]
then

  cd $BASE

  php bin/console update:helper update_3_4

  CURRENT_VERSION=3.4

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.4 ]
then

  cd $BASE

  php bin/console update:helper update_3_5

  CURRENT_VERSION=3.5

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.5 ]
then

  cd $BASE

  php bin/console update:helper update_3_6

  CURRENT_VERSION=3.6

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.6 ]
then

  cd $BASE

  php bin/console update:helper update_3_7

  CURRENT_VERSION=3.7

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.7 ]
then

  cd $BASE

  php bin/console update:helper update_3_8

  CURRENT_VERSION=3.8

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.8 ]
then

  cd $BASE

  php bin/console update:helper update_3_9

  CURRENT_VERSION=3.9

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 3.9 ]
then

  cd $BASE

  php bin/console update:helper update_4_0

  CURRENT_VERSION=4.0

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.0 ]
then

  cd $BASE

  php bin/console update:helper update_4_1

  CURRENT_VERSION=4.1

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.1 ]
then

  cd $BASE

  php bin/console update:helper delete_attribute attribute 2fea2b3186946c79d6b8a41b6bf9db9e
  php bin/console update:helper delete_attribute attribute 9fdd7d26178b1e48aa99167faf5f8809
  php bin/console update:helper delete_attribute attribute 156e613f3d36e53e00935d64232397b2
  php bin/console update:helper delete_attribute attribute 62038c8ceefd87.68268886
  php bin/console update:helper delete_attribute attribute 3ccca38252c41ab1f5c37f82b1e457fa

  php bin/console update:helper delete_entity_type entity_type b2305fb5a7e3dbfa66696a8709fc23c6
  php bin/console update:helper delete_entity_type entity_type 279e520d7cb64142e74762444ab743c0
  php bin/console update:helper delete_entity_type entity_type 1aac03a682fadfd0cae83996b48163e0

  CURRENT_VERSION=4.2

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.2 ]
then

  cd $BASE

  php bin/console update:helper update_4_3

  CURRENT_VERSION=4.3

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.3 ]
then

  cd $BASE

  php bin/console update:helper update_4_4

  CURRENT_VERSION=4.4

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.4 ]
then

  cd $BASE

  php bin/console update:helper update_4_5

  CURRENT_VERSION=4.5

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.5 ]
then

  cd $BASE

  php bin/console update:helper update_4_6

  CURRENT_VERSION=4.6

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.6 ]
then

  cd $BASE

  #Tomislav
  php bin/console user:helper add_admin_user tomislav "57744292" ROLE_ADMIN tomislav@shipshape-solutions.com Tomislav Burić "1,10"

  CURRENT_VERSION=4.7

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.7 ]
then

  cd $BASE

  php bin/console update:helper update_4_8

  CURRENT_VERSION=4.8

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.8 ]
then

  cd $BASE

  php bin/console update:helper update_4_9

  CURRENT_VERSION=4.9

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.9 ]
then

  cd $BASE

  php bin/console cron:run add_update_cron_job 'Transfer to EUR' '2 0 1 1 *' 'Automatically transfer to EUR' 'crmhelper:run type:transfer_to_eur' 1 60
  php bin/console update:helper update_4_91

  CURRENT_VERSION=4.92

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.92 ]
then

  cd $BASE

  php bin/console update:helper update_4_92

  CURRENT_VERSION=4.93

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.93 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities ScommerceBusinessBundle
  php bin/console update:helper update_4_93
  php bin/console user:helper add_privileges_to_entity_type_and_group ROLE_COMMERCE_ADMIN s_route_not_found

  CURRENT_VERSION=4.94

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.94 ]
then

  cd $BASE

  # declined
  php bin/console update:helper delete_attribute attribute 605c8e2b8a4899.55265576

  # drop table entity_state
  php bin/console update:helper update_4_94

  CURRENT_VERSION=4.95

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.95 ]
then

  cd $BASE

  php bin/console update:helper update_4_96

  CURRENT_VERSION=4.96

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.96 ]
then

  cd $BASE

  php bin/console update:helper update_4_97

  CURRENT_VERSION=4.97

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 4.97 ]
then

  cd $BASE

  php bin/console update:helper update_5_0

  if [ $IS_PRODUCTION = 0 ]
  then
    composer require "shipshapesolutions/shapeunittestingbundle:1.x-dev"
    php bin/console update:helper remove_shapebehat
    COMPOSER_DISCARD_CHANGES=true composer remove shipshapesolutions/shapebehat --no-interaction
    chmod +x .git/hooks/pre-commit
  fi

  CURRENT_VERSION=5.0

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.0 ]
then

  cd $BASE

  php bin/console update:helper update_5_1

  CURRENT_VERSION=5.1

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.1 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities AppBundle

  php bin/console update:helper update_5_11

  CURRENT_VERSION=5.11

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.11 ]
then

  cd $BASE

  php bin/console cron:run add_update_cron_job 'Generate core product export xml' '25 4 * * *' 'Generate core product export xml' 'scommercehelper:function type:generate_core_product_export arg1:3' 1 20

  CURRENT_VERSION=5.12

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.12 ]
then

  cd $BASE

  php bin/console update:helper update_5_13

  CURRENT_VERSION=5.13

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.13 ]
then

  cd $BASE

  php bin/console cron:run add_update_cron_job 'Deactivate expired coupons' '2 * * * *' 'Deactivate expired coupons' 'crmhelper:run type:deactivate_expired_coupons' 1 5

  CURRENT_VERSION=5.14

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.14 ]
then

  cd $BASE

  php bin/console update:helper update_5_15

  CURRENT_VERSION=5.15

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.15 ]
then

  cd $BASE

  php bin/console update:helper update_5_16

  CURRENT_VERSION=5.16

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.16 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities CrmBusinessBundle

  php bin/console update:helper update_5_17

  CURRENT_VERSION=5.17

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.17 ]
then

  cd $BASE

  php bin/console update:helper update_5_18

  CURRENT_VERSION=5.18

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.18 ]
then

  cd $BASE

  if [ $IS_PRODUCTION = 0 ]
  then
    export COMPOSER_PROCESS_TIMEOUT=1000
    composer require --dev "brianium/paratest:^0.15"
    php bin/console update:helper update_5_19
  fi

  #Delete not neded
  php bin/console update:helper delete_entity_type entity_type 6078384691f079.53513410
  php bin/console update:helper delete_entity_type entity_type 607952817b9168.14772711
  php bin/console update:helper delete_entity_type entity_type 60e02aa2eca014.54703206

  CURRENT_VERSION=5.19

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.19 ]
then

  cd $BASE

  #Delete not neded on shared_inbox_connection
  php bin/console update:helper delete_attribute attribute 3df155b3b0db6e7d6a94985656252b27

  CURRENT_VERSION=5.20

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.20 ]
then

  cd $BASE

  #DELETE nepotrebne quote_item i order_item atribute

  php bin/console update:helper delete_attribute attribute 60da0efbeebd89.89505837
  php bin/console update:helper delete_attribute attribute 60da0f0b373854.20375781
  php bin/console update:helper delete_attribute attribute 6201a51eb25231.88738368

  php bin/console update:helper delete_attribute attribute 60da0f2de34dd3.09262010
  php bin/console update:helper delete_attribute attribute 60da0f3c7a9466.96331829
  php bin/console update:helper delete_attribute attribute 6201a53b811459.80146057

  php bin/console update:helper delete_attribute attribute 60da0f84e88cd5.71322361
  php bin/console update:helper delete_attribute attribute 60da0f95c64772.15439685
  php bin/console update:helper delete_attribute attribute 6201a5708c87b4.29130174

  php bin/console update:helper delete_attribute attribute e1380d28c686e4cba39ddc266218d358
  php bin/console update:helper delete_attribute attribute b3f7dccdadd8bf245237c33bb890db57
  php bin/console update:helper delete_attribute attribute 263edf0c482ce65c187d2108f120dbab
  php bin/console update:helper delete_attribute attribute 6201485f0108f8.42869468

  php bin/console update:helper delete_attribute attribute 626fa0a3025889.61614298
  php bin/console update:helper delete_attribute attribute 626fa0a42baac3.17035360
  php bin/console update:helper delete_attribute attribute 626fa0a5946bc9.83545016

  php bin/console update:helper delete_attribute attribute 626fa0e03f4686.16225760
  php bin/console update:helper delete_attribute attribute 626fa0e1688e58.59128661
  php bin/console update:helper delete_attribute attribute 626fa0deb58e18.41925984

  php bin/console update:helper delete_attribute attribute 661fed7c64c09be4cfd752e4caaf0bd7
  php bin/console update:helper delete_attribute attribute 8f3dc0936a1c993121770a47f3a27ef8
  php bin/console update:helper delete_attribute attribute 8d8396380537ed67958b32a0031e378b
  php bin/console update:helper delete_attribute attribute 62012ef9502b39.46360385

  php bin/console update:helper delete_attribute attribute 6140e8bfbb73c825960edaeb0c80f78d
  php bin/console update:helper delete_attribute attribute 4ef245d99f3369113fd89a7ccf1f3461
  php bin/console update:helper delete_attribute attribute fc48a892d4fffbea5b426e80d622b906
  php bin/console update:helper delete_attribute attribute 6201215fe0c715.46233163

  php bin/console update:helper delete_attribute attribute 639b054090f833.81208676
  php bin/console update:helper delete_attribute attribute 639b058c044619.79139680

  php bin/console update:helper delete_attribute attribute 661fed7c64c09be4cfd752e4caaf0bd7
  php bin/console update:helper delete_attribute attribute 8f3dc0936a1c993121770a47f3a27ef8
  php bin/console update:helper delete_attribute attribute 8d8396380537ed67958b32a0031e378b
  php bin/console update:helper delete_attribute attribute 62012ef9502b39.46360385

  php bin/console update:helper delete_attribute attribute b87049226a9ba92fbb03f80ae4ee2a2d
  php bin/console update:helper delete_attribute attribute b5f1c2177b674fa9d7f9caabae1e3864

  CURRENT_VERSION=5.21

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.21 ]
then

  cd $BASE

  #DELETE nepotrebne product_discount_catalog_price_entity

  php bin/console update:helper delete_attribute attribute 596176b8677f6a3b2d058a5276235731
  php bin/console update:helper delete_attribute attribute fe4f86f51569749f117fdb296bc1e3a9

  php bin/console update:helper update_5_22

  CURRENT_VERSION=5.22

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.22 ]
then

  cd $BASE

  php bin/console update:helper update_5_23

  CURRENT_VERSION=5.23

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.23 ]
then

  cd $BASE

  php bin/console update:helper update_5_24

  CURRENT_VERSION=5.24

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.24 ]
then

  cd $BASE

  #DELETE int entities

  php bin/console update:helper delete_entity_type entity_type 60479377dea0b5.41521176
  php bin/console update:helper delete_entity_type entity_type 606709b08ef346.87509319
  php bin/console update:helper delete_entity_type entity_type 603cf9568feaf7.73162235
  php bin/console update:helper delete_entity_type entity_type 60588ebe423f10.97493026
  php bin/console update:helper delete_entity_type entity_type 6059fa67543af2.57992392
  php bin/console update:helper delete_entity_type entity_type 603cfad54db023.23181349
  php bin/console update:helper delete_entity_type entity_type 604cd5fdc67146.46713363
  php bin/console update:helper delete_entity_type entity_type 604b4fd3877da1.18000953
  php bin/console update:helper delete_entity_type entity_type 60589a7cdcf100.43744928
  php bin/console update:helper delete_entity_type entity_type 605a0f68eae341.40404817
  php bin/console update:helper delete_entity_type entity_type 6059b8b5e122c6.49395600
  php bin/console update:helper delete_entity_type entity_type 606719cfa425a0.80117919
  php bin/console update:helper delete_entity_type entity_type 60585a46745393.34502450
  php bin/console update:helper delete_entity_type entity_type 6058a642c010f5.29849465
  php bin/console update:helper delete_entity_type entity_type 605b584938cbc4.81596106
  php bin/console update:helper delete_entity_type entity_type 605375f498d5a4.91971120
  php bin/console update:helper delete_entity_type entity_type 606708f535a897.50140065
  php bin/console update:helper delete_entity_type entity_type 6051d030df2056.35447036
  php bin/console update:helper delete_entity_type entity_type 60588d79853df5.11658598
  php bin/console update:helper delete_entity_type entity_type 605a1059e0b455.27261857

  CURRENT_VERSION=5.25

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.25 ]
then

  cd $BASE

  #DELETE int attribute groups
  php bin/console update:helper delete_attribute_group 603cfad57724d3.80985353
  php bin/console update:helper delete_attribute_group 604b4fd3aa48b4.65014054
  php bin/console update:helper delete_attribute_group 6051d0310c7af2.35089968
  php bin/console update:helper delete_attribute_group 60585a469610a7.69592605

  CURRENT_VERSION=5.26

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.26 ]
then

  cd $BASE

  #DELETE int attributes
  php bin/console update:helper delete_attribute attribute 603cfb1fb38999.14119164
  php bin/console update:helper delete_attribute attribute 6051d22e9009e7.39514103
  php bin/console update:helper delete_attribute attribute 60585c053fabc9.57895654
  php bin/console update:helper delete_attribute attribute 60533c3589fba2.28984550

  CURRENT_VERSION=5.27

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.27 ]
then

  cd $BASE

  #DELETE int attribute groups
  php bin/console update:helper delete_attribute_group 603cf956b99395.24291075

  CURRENT_VERSION=5.28

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.28 ]
then

  cd $BASE

  #check cron jobs
  php bin/console update:helper update_5_29

  CURRENT_VERSION=5.29

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.29 ]
then

  cd $BASE

  php bin/console admin:sync import
  composer rebuild-entities CrmBusinessBundle

  #DELETE cash_price
  php bin/console update:helper delete_attribute attribute affbacdf3aac8cad37d7bc54dbee9194
  php bin/console update:helper delete_attribute attribute 5de96b4bcd46c87e2bfad682a3ab3b0c

  CURRENT_VERSION=5.30

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.30 ]
then

  cd $BASE

  php bin/console update:helper update_5_31

  CURRENT_VERSION=5.31

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi
if [ $CURRENT_VERSION = 5.31 ]
then

  cd $BASE

  php bin/console update:helper update_5_32

  CURRENT_VERSION=5.32

  sed -i '/^CORE_VERSION/d' .env
  sed -i "1s|^|CORE_VERSION=$CURRENT_VERSION\n|" .env

  printf "\n\nUpdated to version $CURRENT_VERSION...\n\n"
fi

#//entity_type ima smeca u onim doctrine textovima
#rijesit smetrala

cd $BASE

printf "\nRebuilding entities..."
composer rebuild-entities all

printf "\nRebuilding assets..."
cd $APPBUNDLE_BASE
chmod +x Resources/scripts/rebuild.sh && Resources/scripts/rebuild.sh

cd $BASE

printf "\nOptimizing composer autoload..."
composer dump-autoload --optimize &> /dev/null

printf "\nClearing caches..."
cd $BASE

rm -rf var/cache/_sp
mv var/cache/sp var/cache/_sp
rm -rf var/cache/_sp

php bin/console cache:clear
php bin/console cache:clear --env=prod
php bin/console admin:entity clear_backend_cache
php bin/console update:helper update_default_settings

#php bin/console admin:update_to_uid
#php bin/console admin:entity add_uid role
#php bin/console admin:entity add_uid core_language
#php bin/console admin:entity add_uid region
#php bin/console admin:entity add_uid country
php bin/console admin:entity rebuild_indexes
php bin/console user:helper recreate_superadmin_privileges
php bin/console db_update:helper rebuild_procedures_views
php bin/console db_update:helper insert_default_codebooks
php bin/console update:helper update_missing_uid

printf "\n\nCurrent version $CURRENT_VERSION...\n\n"

php bin/console update:helper generate_account_and_contact_for_all_admin
