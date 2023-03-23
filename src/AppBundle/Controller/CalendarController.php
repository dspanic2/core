<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity;
use AppBundle\Helpers\EntityHelper;
use AppBundle\Managers\BlockManager;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\HelperManager;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use AppBundle\Managers\CalendarManager;

class CalendarController extends AbstractController
{

    /** @var CalendarManager $calendarManager */
    protected $calendarManager;
    /** @var  BlockManager $entityManager*/
    protected $blockManager;
    /** @var  EntityManager $entityManager*/
    protected $entityManager;

    protected function initialize()
    {
        parent::initialize();
        $this->calendarManager = $this->getContainer()->get("calendar_manager");
        $this->blockManager = $this->getContainer()->get('block_manager');
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    /**
     * @Route("/calendar/drop/data", name="calendar_drop_data")
     */
    public function calendarDropData(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (!isset($p["block_id"]) || empty($p["block_id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Block id is not defined')));
        }
        if (!isset($p["id"]) || empty($p["id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Entity id is not defined')));
        }
        if (!isset($p["start"]) || empty($p["start"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Start date is not defined')));
        }
        if (!isset($p["entity_type_id"]) || empty($p["entity_type_id"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Entity type is not defined')));
        }
        if (!isset($p["start_attribute_code"]) || empty($p["start_attribute_code"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Start attribute is not defined')));
        }
        /*if (!isset($p["end_attribute_code"]) || empty($p["end_attribute_code"])) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('End attribute is not defined')));
        }*/

        /** @var Entity\PageBlock $block */
        $block = $this->blockManager->getBlockById($p["block_id"]);
        if (empty($block)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Block is missing')));
        }

        $content = json_decode($block->getContent());
        if (empty($content)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Block content is missing')));
        }

        $entityType = $this->entityManager->getEntityTypeById($p["entity_type_id"]);

        $entity = $this->entityManager->getEntityByEntityTypeAndId($entityType, $p["id"]);
        if (empty($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Entity is missing')));
        }

        /**
         * Implement this listener to check if the entity can be changed or not anywhere
         */
        if (!$this->calendarManager->dispatchCalendarDragAndDrop($entity)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error occured'), 'message' => $this->translator->trans('Dates cannot be changed')));
        }

        $getterStart = EntityHelper::makeGetter($p["start_attribute_code"]);
        $setterStart = EntityHelper::makeSetter($p["start_attribute_code"]);
        $currentDateStart = $entity->$getterStart();

        $newBaseDateStart = \DateTime::createFromFormat('d-m-Y H:i', $p["start"]);
        $newDateStart = \DateTime::createFromFormat('d-m-Y H:i', $newBaseDateStart->format("d-m-Y")." ".$currentDateStart->format("H:i"));

        $entity->$setterStart($newDateStart);

        if(isset($p["end_attribute_code"]) && !empty($p["end_attribute_code"])){
            $getterEnd = EntityHelper::makeGetter($p["end_attribute_code"]);
            $setterEnd = EntityHelper::makeSetter($p["end_attribute_code"]);
            $currentDateEnd = $entity->$getterEnd();
            $diff = $currentDateStart->diff($currentDateEnd);
            $newDateEnd = clone $newDateStart;
            $newDateEnd = $newDateEnd->add($diff);
            $entity->$setterEnd($newDateEnd);
        }

        $this->entityManager->saveEntity($entity);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Dates have been changed')));
    }

    /**
     * @Route("/calendar/fetch/data", name="calendar_fetch_data")
     */
    public function calendarFetchData(Request $request)
    {
        $this->initialize();

        $_GET["parent_entity_id"] = null;
        $referer = $request->headers->get('referer');
        if (!empty($referer) && strpos($referer, "form")) {
            if (strpos($referer, "?") != false) {
                $referer = explode("?", $referer);
                $referer = $referer[0];
            }
            $referer = explode("/", $referer);
            $_GET["parent_entity_id"] = $referer[count($referer)-1];
            if (!is_numeric($_GET["parent_entity_id"])) {
                $_GET["parent_entity_id"] = null;
            }
        }

        $raw_data = $this->calendarManager->getCalendarData($_GET);

        $raw_data = json_encode($raw_data);

        return new Response($raw_data);
    }

    /**
     * Generate calendar event ICAL for welpAction
     * @Route("/calendar/ical_export", name="ical_export")
     */
    public function icalExportAction(Request $request)
    {
        $_GET["parent_entity_id"] = null;
        $referer = $request->headers->get('referer');
        if (!empty($referer) && strpos($referer, "form")) {
            $referer = explode("/", $referer);
            $_GET["parent_entity_id"] = $referer[count($referer)-1];
            if (!is_numeric($_GET["parent_entity_id"])) {
                $_GET["parent_entity_id"] = null;
            }
        }

        $q = $_GET;

        if (!isset($q["u"]) || empty($q["u"])) {
            throw $this->createAccessDeniedException('Access denied');
        }
        if (!isset($q["b"]) || empty($q["b"])) {
            throw $this->createAccessDeniedException('Access denied');
        }

        /** @var HelperManager $helperManager */
        $helperManager = $this->getContainer()->get("helper_manager");

        /** @var Entity\CoreUserEntity $user */
        $user = $helperManager->getUserBySalt($q["u"]);
        if (empty($user)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        $helperManager->loginAnonymus($request, $user->getUsername());

        $this->initialize();

        /** @var Entity\PageBlock $block */
        $block = $this->blockManager->getBlockById($q["b"]);
        if (empty($block)) {
            throw $this->createAccessDeniedException('Access denied');
        }

        $data = array();
        $data["block_id"] = $q["b"];

        $now = new \DateTime();

        $data["start"] = $now->format("Y-m-d");
        $now->add(new \DateInterval('P1M'));
        $data["end"] = $now->format("Y-m-d");

        $raw_data = $this->calendarManager->getCalendarData($data);

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get("security.token_storage");
        $tokenStorage->setToken(null);
        $request->getSession()->invalidate();

        $calendarResponse = $this->calendarManager->generateIcal($raw_data);

        return $calendarResponse;
    }
}
