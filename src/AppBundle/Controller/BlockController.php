<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Entity\PageBlock;
use AppBundle\Helpers\StringHelper;
use AppBundle\Managers\BlockManager;
use Proxies\__CG__\AppBundle\Entity\AttributeGroup;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;

class BlockController extends AbstractController
{
    /**@var BlockManager $blockManager */
    protected $blockManager;

    protected function initialize()
    {
        parent::initialize();
        $this->blockManager = $this->getContainer()->get('block_manager');
    }

    /**
     * @Route("/block/modal", name="block_modal_view")
     */
    public function blockModalAction(Request $request)
    {
        $data = array();
        $data["id"] = $request->get('id');

        $buttonAction = "refresh";
        if (!empty($request->get('action'))) {
            $buttonAction = $request->get('action');
        }

        $data["type"] = "form";
        if (!empty($request->get('type'))) {
            $data["type"] = $request->get('type');
        }

        $data["subtype"] = $data["type"];
        if (!empty($request->get('subtype'))) {
            $data["subtype"] = $request->get('subtype');
        }

        $data["page"] = null;
        $data["is_modal"] = true;
        $data["parent"]["id"] = $request->get("pid");
        $data["parent"]["attributeSetCode"] = $request->get("ptype");

        $callback = $request->get("callback");
        if (!empty($callback)) {
            $data["callback"] = $callback;
        }

        if ($data["type"] == "form") {
            $data["page"]["buttons"] = '[{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"' . $buttonAction . '"},{"type":"button","name":"Close","class":"btn-default btn-red","url":"","action":"dismiss-modal"}]';
        } //      $data["page"]["buttons"] = '[{"type":"button","name":"Save","class":"btn-primary btn-blue","url":"","action":"refresh"},{"type":"button","name":"Close","class":"btn-default btn-red","url":"","action":"dismiss-modal"}]';
        else {
            $data["page"]["buttons"] = '[{"type":"button","name":"Close","class":"btn-default btn-red","url":"","action":"dismiss-modal"}]';
        }
        $block_id = $request->get('block_id');

        $this->initialize();

        /**
         * Get default form block id from attribute_set
         */
        if (empty($block_id)) {
            $attribute_set_code = $request->get('attribute_set_code');
            $block_id = $this->blockManager->getDefautlEditFromIdByAttributeSet($attribute_set_code);

            if (empty($block_id)) {
                return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal') . " " . $this->translator->trans('Edit form missing for attribute set') . ": " . $attribute_set_code));
            }
        }

        /** @var PageBlock $block */
        $block = $this->blockManager->getBlockById($block_id);
        if (empty($block)) {
            return $this->twig->render('AppBundle:Block:block_empty.html.twig', array("data" => $data));
        }

        if ($block->getType() == "list_view") {
            $data["type"] = "list";
            $html = $this->blockManager->generateBlockHtmlV2($data, $block);
        } else {
            $html = $this->blockManager->generateBlockHtml($data, $block_id);
        }

        /** @var PageBlock $block */
        $block = $this->blockManager->getBlockById($block_id);

        $uid = StringHelper::removeAllSpecialCharacters($block->getUid());

        $html = $this->renderView('AppBundle:Includes:modal.html.twig', array("class" => "uid_" . $uid, "html" => $html, "title" => $this->translator->trans($block->getTitle())));
        if (empty($html)) {
            return new JsonResponse(array('error' => true, 'message' => $this->translator->trans('Error opening modal')));
        }

        return new JsonResponse(array('error' => false, 'html' => $html));
    }

    /**
     * @Route("/block", name="block_view")
     */
    public function blockAction(Request $request)
    {
        $this->initialize();

        $data = $request->get('data');
        $block_id = $request->get('block_id');

        $html = $this->blockManager->generateBlockHtml($data, $block_id);

        if (empty($html)) {
            return new Response();
        }

        return new Response($html);
    }

    /**
     * @Route("/block/filter", name="block_view_filter")
     */
    public function blockFilterAction(Request $request)
    {
        $array = array();

        $array[] = array("id" => 1, "text" => "YEAR", "checked" => false, "hasChildren" => false);

        return new JsonResponse($array);
    }
}
