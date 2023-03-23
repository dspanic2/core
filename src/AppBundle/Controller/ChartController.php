<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Managers\BlockManager;
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

class ChartController extends AbstractController
{
    /**@var BlockManager $blockManager */
    protected $blockManager;

    protected function initialize()
    {
        parent::initialize();
        $this->blockManager = $this->getContainer()->get('block_manager');
    }

    /**
     * @Route("/chart/data/{block_id}", name="get_chart_data")
     *
     */
    public function getChartData(Request $request, $block_id)
    {
        $this->initialize();
        $pageBlock = $this->blockManager->getBlockById($block_id);
        $p = $_POST;

        if (isset($p["id"])) {
            $data["id"] = $p["id"];
        }

        $data["block"] = $pageBlock;

        $block = $this->blockManager->getBlock($pageBlock, $data);


        $data = $block->FilterData();
        return new JsonResponse(array('error' => false, "data" => $data));
    }



    /**
     * @Route("/chart/filter/set/{block_id}", name="chart_filter_set")
     */
    public function chartFilterSetAction(Request $request, $block_id)
    {
        $this->initialize();

        unset($_SESSION["filter_block_".$block_id]);
        $_SESSION["filter_block_".$block_id] = $_POST;


        return new JsonResponse(array('error' => false, 'message' => $this->translator->trans('Filter updated')));
    }
}
