<?php

namespace App\PublicModule\Presenters;

use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;

final class HomepagePresenter extends BasePresenter
{
    public $BootstrapForm;
    public $db;
    public $cc;

    public function __construct(BootstrapForm $BootstrapForm, Nette\Database\Context $db)
    {
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
    }

    public function renderDefault()
    {
    }

    public function actionContact()
    {

    }

    protected function createComponentContactForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')
            ->addRule(Form::MIN_LENGTH, 'Minimálny počet znakov v mene je 5.', 5)
            ->addRule(Form::MAX_LENGTH, 'Maximálny počet znakov v mene je 50.', 50)
            ->setRequired(true);

        $form->addEmail('email')
            ->addRule(Form::MIN_LENGTH, 'Minimálny počet znakov pre e-mail je 5.', 5)
            ->addRule(Form::MAX_LENGTH, 'Maximálny počet znakov pre e-mail je 50.', 50)
            ->setRequired(true);

        $form->addText('phone')
            ->setRequired(true)
            ->addRule(Form::PATTERN, 'Nesprávny formát čísla.', '[0-9\+/s]+')
            ->addRule(Form::MAX_LENGTH, 'Maximálny počet znakov tel. čísla je 15.',15)
            ->addRule(Form::MIN_LENGTH,'Minimálny počet znakov tel. čísla je 9.',9);

        $form->addTextArea('message')
            ->setRequired(true)
            ->addRule(Form::MAX_LENGTH, 'Maximálny počet znakov správy je 500.',500)
            ->addRule(Form::MIN_LENGTH,'Minimálny počet znakov správy je 15.',15);

        $form->onSuccess[] = [$this, 'ContactFormSucceeded'];

        return $form;
    }

    public function ContactFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->sendMail($values->name, $values->email, $values->phone, $values->message);
            $this->flashMessage('Úspešne ste odoslali kontaktný formulár.', 'success');
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
