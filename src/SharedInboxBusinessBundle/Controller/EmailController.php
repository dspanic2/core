<?php

namespace SharedInboxBusinessBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use SharedInboxBusinessBundle\Entity\EmailEntity;
use SharedInboxBusinessBundle\Managers\EmailManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class EmailController extends AbstractController
{
    /** @var EmailManager $emailManager */
    protected $emailManager;

    protected function initialize()
    {
        parent::initialize();
        $this->emailManager = $this->getContainer()->get("email_manager");
    }

    /**
     * @Route("/email/reply_form", name="email_reply_form")
     * @param Request $request
     * @return JsonResponse
     */
    public function getSendToClientModalAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["email_id"]) || empty($p["email_id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Email id not received")));
        }

        /** @var EmailEntity $email */
        $email = $this->emailManager->getEmailById($p["email_id"]);
        if (empty($email)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Email not found")));
        }

        $html = $this->renderView("SharedInboxBusinessBundle:Includes:email_reply_form.html.twig",
            ["email_id" => $p["email_id"]]
        );

        return new JsonResponse(array("error" => false, "html" => $html));
    }

    /**
     * @Route("/email/reply", name="email_reply")
     * @param Request $request
     * @return JsonResponse
     */
    public function emailReplyAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["email_id"]) || empty($p["email_id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Email id not received")));
        }
        if (!isset($p["email_message"]) || empty($p["email_message"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Email message not received")));
        }

        /** @var EmailEntity $email */
        $email = $this->emailManager->getEmailById($p["email_id"]);
        if (empty($email)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Email not found")));
        }

        $this->emailManager->sendReplyEmail($email, $p["email_message"]);

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Email sent")));
    }
}
