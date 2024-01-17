#!/bin/bash

setup_mautic() {
    [ -z "${MAUTIC_URL}" ] && MAUTIC_URL="https://${DDEV_HOSTNAME}"
    [ -z "${PHPMYADMIN_URL}" ] && PHPMYADMIN_URL="https://${DDEV_HOSTNAME}:8037"
    [ -z "${MAILHOG_URL}" ] && MAILHOG_URL="https://${DDEV_HOSTNAME}:8026"

    printf "Installing Mautic Composer dependencies...\n"
    composer install

    cp ./.ddev/local.config.php.dist ./config/local.php
    cp ./.ddev/.env.test.local ./.env.test.local

    printf "Installing Mautic...\n"
    php bin/console mautic:install "${MAUTIC_URL}"
    php bin/console cache:warmup --no-interaction --env=dev

    printf "Enabling plugins...\n"
    php bin/console mautic:plugins:reload

    tput setaf 2
    printf "All done! Here's some useful information:\n"
    printf "🔒 The default login is admin / Maut1cR0cks!\n"
    printf "🌐 To open the Mautic instance, go to ${MAUTIC_URL} in your browser.\n"
    printf "🌐 To open PHPMyAdmin for managing the database, go to ${PHPMYADMIN_URL} in your browser.\n"
    printf "🌐 To open MailHog for seeing all emails that Mautic sent, go to ${MAILHOG_URL} in your browser.\n"
    printf "🚀 Run \"ddev exec composer test\" to run PHPUnit tests.\n"
    printf "🚀 Run \"ddev exec bin/console COMMAND\" (like mautic:segments:update) to use the Mautic CLI. For an overview of all available CLI commands, go to https://mau.tc/cli\n"
    printf "🔴 If you want to stop the instance, simply run \"ddev stop\".\n"
    tput sgr0
}

# Check if the user has indicated their preference for the Mautic installation
# already (DDEV-managed or self-managed)
if ! test -f ./.ddev/mautic-preference
then
    tput setab 3
    tput setaf 0
    printf "Do you want us to set up the Mautic instance for you with the recommended settings for DDEV?\n"
    printf "If you answer \"no\", you will have to set up the Mautic instance yourself."
    tput sgr0
    printf "\nAnswer [yes/no]: "
    read MAUTIC_PREF

    if [ $MAUTIC_PREF == "yes" ] || [ -n $GITPOD_HEADLESS ];
    then
        printf "Okay, setting up your Mautic instance... 🚀\n"
        echo "ddev-managed" > ./.ddev/mautic-preference
        setup_mautic
    else
        printf "Okay, you'll have to set up the Mautic instance yourself. That's what pros do, right? Good luck! 🚀\n"
        echo "unmanaged" > ./.ddev/mautic-preference
    fi
fi
