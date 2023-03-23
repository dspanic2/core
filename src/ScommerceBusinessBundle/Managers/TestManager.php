<?php

namespace ScommerceBusinessBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Managers\MailManager;
use CrmBusinessBundle\Entity\OrderEntity;

class TestManager extends AbstractBaseManager
{

    /**
     * @param OrderEntity $order
     */
    public function testOrderEmail(OrderEntity $order)
    {

        /** @var MailManager $mailManager */
        $mailManager = $this->container->get("mail_manager");

        $email = "davor.spanic@gmail.com";
        $email2 = "davor.spanic@shipshape.hr";

        $bcc = array('email' => $email2, 'name' => $email2);

        $mailManager->sendEmail(array('email' => $email, 'name' => $email), null, $bcc, null, $this->translator->trans('Order confirmation'), "", "order_confirmation", array("order" => $order), null, array(), $order->getStoreId());
    }

    public function testRegisterEmail()
    {

        /** @var MailManager $mailManager */
        $mailManager = $this->container->get("mail_manager");

        $email = "davor.spanic@gmail.com";
        $email2 = "davor.spanic@shipshape.hr";

        $data["email"] = "davor.spanic@gmail.com";
        $data["password"] = "57744292";
        $sendPassword = true;

        $bcc = array('email' => $email2, 'name' => $email2);

        $mailManager->sendEmail(array('email' => $email, 'name' => $email), null, $bcc, null, $this->translator->trans('New account'), "", "new_account_legal", array("user" => $data, "password" => $data["password"], "send_password" => $sendPassword));
    }

    /**
     * @param $dirPath
     * @return bool
     */
    public function deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);

        return true;
    }

    /**
     * @return bool
     */
    public function cleanProductImages()
    {
        $basePath = $_ENV["WEB_PATH"];

        $images = $this->getAllImages();

        $dir = $basePath . "/Documents/Products";

        $subdirs = scandir($dir);
        foreach ($subdirs as $d) {
            if ($d == "." || $d == ".." || !is_dir($dir . "/" . $d)) {
                continue;
            }

            if (!file_exists($dir . "/" . $d)) {
                continue;
            }

            if (!array_key_exists($d, $images)) {
                $this->deleteDir($dir . "/" . $d);
                continue;
            }

            $files = scandir($dir . "/" . $d);
            foreach ($files as $f) {
                if ($f == "." || $f == "..") {
                    continue;
                } elseif (is_dir($dir . "/" . $d . "/" . $f)) {
                    $this->deleteDir($dir . "/" . $d . "/" . $f);
                    continue;
                }

                if (!in_array($d . "/" . $f, $images[$d])) {
                    unlink($dir . "/" . $d . "/" . $f);
                }
            }
        }

        return true;
    }
}
