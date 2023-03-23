<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use AppBundle\Managers\NoteManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class NotesController extends AbstractController
{
    /**@var FormManager $formManager */
    protected $formManager;
    /**@var EntityManager $entityManager */
    protected $entityManager;
    /**@var NoteManager $noteManager */
    protected $noteManager;

    protected $type;

    protected function initialize()
    {
        parent::initialize();
        $this->type = "note";
        $this->entityManager = $this->getContainer()->get("entity_manager");
        $this->noteManager = $this->getContainer()->get("note_manager");
        $factoryManager = $this->getContainer()->get("factory_manager");
        $this->formManager = $factoryManager->loadFormManager($this->type);
    }

    /**
     * @Route("/notes/get-content", name="get-notes-content")
     * @Method("POST")
     * @return JsonResponse
     */
    public function getNotesContent()
    {
        $p = $_POST;

        $this->initialize();

        $relatedEntityType = $p["relatedEntityType"];
        $relatedEntityId = $p["relatedEntityId"];

        $notes = $this->noteManager->getNotesForEntity($relatedEntityType, $relatedEntityId);

        $html = $this->renderView("AppBundle:Includes:notes_content.html.twig", Array("notes" => $notes));

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "html" => $html));
    }

    /**
     * @Route("/notes/add_note", name="add_note")
     * @Method("POST")
     * @return JsonResponse
     * @throws \Exception
     */
    public function saveAction()
    {
        $this->initialize();

        $request = $this->container->get('request_stack')->getCurrentRequest();

        $session = $request->getSession();
        $session->set("_locale_type", "backend");

        $helperManager = $this->container->get('helper_manager');
        $currentUser = $helperManager->getCurrentUser();

        /**@var Translator $translator */
        $translator = $this->container->get('translator');
        $translator->setLocale($currentUser->getCoreLanguage()->getCode());

        if (isset($token) && !empty($token) && is_object($token->getUser())) {
            $user = $token->getUser();
            $request->setLocale($user->getCoreLanguage()->getCode());
            $request->getSession()->set('_locale', $user->getCoreLanguage()->getCode());
        }

        $entityValidate = $this->formManager->validateFormModel($this->type, $_POST);
        if ($entityValidate->getIsValid() == false) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $entityValidate->getMessage()));
        }

        $entity = $this->formManager->saveFormModel($this->type, $_POST);
        if (empty($entity)) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("There has been an error")));
        }

        $html = $this->renderView("AppBundle:Includes:notes_single.html.twig", Array("e" => $entity));

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Form has been submitted"), "html" => $html));
    }

    /**
     * @Route("/notes/toggle_like", name="toggle_like")
     * @Method("POST")
     */
    public function likeAction(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Note id cannot be empty")));
        }

        $content = $this->noteManager->toggleLike($p["id"]);
        if (!empty($content)) {
            $content = implode("<br>", $content);
        }

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Successfully toggled like"), "content" => $content));
    }

    /**
     * @Route("/notes/delete_note", name="delete_note")
     * @Method("POST")
     */
    public function deleteAction(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array("error" => true, "title" => $this->translator->trans("Error occurred"), "message" => $this->translator->trans("Note id cannot be empty")));
        }

        $this->noteManager->deleteLikesForNote($p["id"]);

        $this->formManager->deleteFormModel($this->type, $p["id"]);

        return new JsonResponse(array("error" => false, "title" => $this->translator->trans("Success"), "message" => $this->translator->trans("Successfully deleted note")));
    }
}
