<?php

namespace Models;

class Mail extends \System\Model {

    public function sendMail($email, $subject, $body, $options = array()) {
        $mailer = new \PHPMailer();
        $mailer->IsSMTP();
        $mailer->Host = $this->di['config']['mail']['host'];
        $mailer->Port = $this->di['config']['mail']['port'];
        $mailer->SMTPSecure = 'tls';
        $mailer->SMTPAuth = true;
        $mailer->Username = $this->di['config']['mail']['username'];
        $mailer->Password = $this->di['config']['mail']['password'];

        if (isset($options['replyTo'])) {
            $mailer->addReplyTo($options['replyTo']);
        }

        $mailer->From = $this->di['config']['mail']['from'];
        $mailer->FromName = $this->di['config']['mail']['fromName'];

        $mailer->Subject = $subject;
        $mailer->Body = $body;
        
        if (isset($options['isHTML'])) {
            $mailer->IsHTML($options['isHTML']);
        } else {
            $mailer->IsHTML(true);
        }
        
        $mailer->AddAddress($email);

        if (!$mailer->Send()) {
            throw new MailSendException('Error during send email: ' . $mailer->ErrorInfo);
        }
    }

    public function sendRestorePassword($email, $params) {
        $subject = $this->di['t']->_('Retrieve password');

        $path = $this->_getTemplatePath('restorePassword.htm');
        $content = $this->_fetchTemplate($path, array(
            'email' => $email,
            'resetUrl' => $params['resetUrl']
        ));

        $this->sendMail($email, $subject, $this->_fetchWrapper($subject, $content));
    }
    
    public function sendUnlockPassword($email, $unlockUrl) {
        $subject = $this->di['t']->_('Account Locked');

        $path = $this->_getTemplatePath('unlockPassword.htm');
        $content = $this->_fetchTemplate($path, array(
            'unlockUrl' => $unlockUrl
        ));

        $this->sendMail($email, $subject, $this->_fetchWrapper($subject, $content));
    }

    public function sendSupportTicket($id, $name, $email, $type, $message, $browser, $os) {
        $subject = $this->di['t']->_('Ticket #%1$s - %2$s - %3$s', array(
            $id,
            $type,
            $email
        ));

        $path = $this->_getTemplatePath('support.htm');
        $content = $this->_fetchTemplate($path, array(
            'name' => $name,
            'email' => $email,
            'browser' => $browser,
            'os' => $os,
            'message' => $message
        ));

        $this->sendMail($this->di['config']['supportEmail'], $subject, $content, array(
            'replyTo' => $email,
            'isHTML' => false
        ));
    }

    private function _fetchWrapper($title, $content) {
        $footerPath = $this->_getTemplatePath('footer.htm');

        return $this->_fetchTemplate('mail/template.htm', array(
                    'emailTitle' => $title,
                    'emailContent' => $content,
                    'emailFooter' => $this->_fetchTemplate($footerPath),
                    'emailLogoUrl' => $this->di['config']['mail']['logoUrl'],
                    'emailLogoImageUrl' => $this->di['config']['mail']['logoImageUrl']
        ));
    }

    private function _getTemplatePath($template) {
        return 'mail/' . $this->di['locale'] . '/' . $template;
    }

    private function _fetchTemplate($path, $keys = array()) {
        $view = $this->di['view'];
        return $view->fetch($path, $keys);
    }

}

class MailSendException extends \Exception {
    
}
