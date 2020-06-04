<?php

namespace App\PublicModule\Presenters;

use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;

final class HomepagePresenter extends BasePresenter
{
    public $BootstrapForm;
    public $db;
    public $cc;
    public $r;
    public $response;

    public function __construct(ProtectedForm $BootstrapForm, Nette\Database\Context $db, Nette\Http\Request $request, Nette\Http\Response $response)
    {
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
        $this->r = $request;
        $this->response = $response;
    }

    public function renderDefault()
    {
    }

    public function actionContact()
    {
    }

    protected function createComponentContactForm()
    {
        $form = $this->form->create();

        $form->addText('name')
            ->addRule(Form::MIN_LENGTH, $this->translator->translate('contact.min_name', ['count' => 5]), 5)
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('contact.max_name', ['count' => 50]), 50)
            ->setRequired(true);

        $form->addEmail('email')
            ->addRule(Form::MIN_LENGTH, $this->translator->translate('contact.min_mail', ['count' => 5]), 5)
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('contact.max_mail', ['count' => 50]), 50)
            ->setRequired(true);

        $form->addText('phone')
            ->setRequired(true)
            ->addRule(Form::PATTERN, $this->translator->translate('contact.incorrect_tel'), '[0-9\+/s]+')
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('contact.max_tel', ['count' => 15]),15)
            ->addRule(Form::MIN_LENGTH,$this->translator->translate('contact.min_tel', ['count' => 9]),9);

        $form->addTextArea('message')
            ->setRequired(true)
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('contact.max_msg', ['count' => 500]),500)
            ->addRule(Form::MIN_LENGTH,$this->translator->translate('contact.min_msg', ['count' => 15]),15);

        $form->onSuccess[] = [$this, 'ContactFormSucceeded'];

        return $form;
    }

    public function ContactFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->sendMail($values->name, $values->email, $values->phone, $values->message);
            $this->flashMessage($this->translator->translate('contact.success_send'), 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }
    }

    private function sendMail($name, $email, $phone, $message)
    {
        $name = iconv("utf-8", "us-ascii//TRANSLIT", $name);
        $name = preg_replace("/[^a-zA-Z\ ]/i", "", $name);

        $message = $this->pre($message);

        $mail = new Message;
        $mail->setFrom($name.' <'.$email.'>')
            ->addTo('data@bratislava.sk')
            ->setSubject('Nová kontaktná správa od: '. $name)
            ->setHtmlBody("Dobrý deň,<br><br>z portálu <b>OpenData Bratislava</b> prišla nová kontaktná správa.<br><br><b>Odosielateľ:</b> ".$name." - ".$email."<br><b>Tel. č.:</b> ".$phone."<br><b>Správa:</b><br>".$message);

        $mailer = new SendmailMailer;
        $mailer->send($mail);

        /*$mailer = new Nette\Mail\SmtpMailer([
            'host' => 'smtp.example.com',
            'username' => 'username',
            'password' => '***',
            'secure' => 'ssl',
        ]);
        $mailer->send($mail);*/
    }

    private function pre($text)
    {
        $text = str_replace("\n.", "\n..", $text);
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = str_replace("\n", "<br />", $text);

        return $text;
    }
}
