drush --root=$DOCROOT rr
drush --root=$DOCROOT cc all
drush --root=$DOCROOT en environment -y
drush --root=$DOCROOT en environment_indicator -y
drush --root=$DOCROOT en dkan_tools -y
drush --root=$DOCROOT env-switch $ENV --force
drush --root=$DOCROOT updb -y
[ -f $DOCROOT/../src/script/deploy.custom.sh ] && $DOCROOT/../src/script/deploy.custom.sh $DOCROOT $ENV || echo "No custom deployment script."
