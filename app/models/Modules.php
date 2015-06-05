<?php

namespace Models;

use System\DI;

/**
 * Description of Modules
 *
 * @author root
 */
class Modules
{

    const SMS = 'sms';
    const CALLS = 'calls';
    const LOCATIONS = 'locations';
    const BROWSER_HISTORY = 'browserHistory';
    const BROWSER_BOOKMARKS = 'browserBookmarks';
    const CONTACTS = 'contacts';
    const CALENDAR = 'calendar';
    const PHOTOS = 'photos';
    const VIBER = 'viber';
    const WHATSAPP = 'whatsapp';
    const VIDEOS = 'videos';
    const SKYPE = 'skype';
    const FACEBOOK = 'facebook';
    const VK = 'vk';
    const EMAILS = 'emails';
    const APPLICATIONS = 'applications';
    const KEYLOGGER = 'keylogger';
    const SMS_COMMANDS = 'smsCommands';
    const SETTINGS = 'settings';
    const INSTAGRAM = 'instagram';
    const KIK = 'kik';
    const NOTES = 'notes';
    const SNAPCHAT = 'snapchat';
    

    private static $moduleCheckMethods = array(
        self::LOCATIONS => 'isLocationsActive',
        self::BROWSER_BOOKMARKS => 'isBrowserBookmarksActive',
        self::BROWSER_HISTORY => 'isBrowserHistoryActive',
        self::CALENDAR => 'isCalendarActive',
        self::CONTACTS => 'isContactsActive',
        self::KEYLOGGER => 'isKeyloggerActive',
        self::PHOTOS => 'isPhotosActive',
        self::VIDEOS => 'isVideosActive',
        self::VIBER => 'isViberActive',
        self::SKYPE => 'isSkypeActive',
        self::WHATSAPP => 'isWhatsappActive',
        self::FACEBOOK => 'isFacebookActive',
        self::KIK => 'isKikActive',
        self::VK => 'isVkActive',
        self::EMAILS => 'isEmailsActive',
        self::APPLICATIONS => 'isApplicationsActive',
        self::SMS_COMMANDS => 'isSmsCommandsActive',
        self::INSTAGRAM => 'isInstagramActive',
        self::NOTES => 'isNotesActive',
        self::SNAPCHAT => 'isSnapchatActive'
    );
    
    protected $di;
    
    public function __construct(DI $di)
    {
        $this->di = $di;
    }

    public function isModuleActive($name)
    {
        if (isset(self::$moduleCheckMethods[$name])) {
            return call_user_func_array(array(
                '\CS\Devices\DeviceOptions',
                self::$moduleCheckMethods[$name]
            ), array(
                $this->di['currentDevice']['os'],
                $this->di['currentDevice']['os_version'],
                $this->di['currentDevice']['app_version']
            ));
        }

        return true;
    }

}
