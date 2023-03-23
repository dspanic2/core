<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\CoreLanguageEntity;
use AppBundle\Managers\TranslationManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class TranslationController extends AbstractController
{
    /** @var TranslationManager $translationManager */
    protected $translationManager;

    protected function initialize()
    {
        parent::initialize();
        $this->translationManager = $this->getContainer()->get("translation_manager");
    }

    /**
     * @Route("/translation/regenerate_translation", name="regenerate_translation")
     */
    public function regenerateTranslationAction(Request $request)
    {
        $this->initialize();

        $p = $_POST;

        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Id is not defined')));
        }

        /** @var CoreLanguageEntity $coreLanguage */
        $coreLanguage = $this->translationManager->getCoreLanguageById($p["id"]);

        if (empty($coreLanguage)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Core language does not exist')));
        }

        $bundles = $this->getParameter('kernel.bundles');
        foreach ($bundles as $key => $value) {
            if ($key == "AppBundle") {
                $this->translationManager->createTranslationForBundle($key, $coreLanguage->getCode());
                break;
            }
        }
        foreach ($bundles as $key => $value) {
            if (strpos(strtolower($key), "business") !== false) {
                $this->translationManager->createTranslationForBundle($key, $coreLanguage->getCode());
            }
        }

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Translate language'), 'message' => $this->translator->trans('All bundles translated')));
    }
}
