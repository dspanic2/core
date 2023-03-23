<?php

namespace SharedInboxBusinessBundle\Blocks;

use AppBundle\Abstracts\JsonResponse;
use AppBundle\Entity\PageBlock;
use AppBundle\Managers\BlockManager;
use AppBundle\Abstracts\AbstractBaseBlock;
use SharedInboxBusinessBundle\Managers\EmailManager;

class EmailViewBlock extends AbstractBaseBlock
{
    /** @var EmailManager $emailManager */
    protected $emailManager;

    /**
     * @return string
     */
    public function GetPageBlockTemplate()
    {
        return "SharedInboxBusinessBundle:Block:email_view.html.twig";
    }

    /**
     * @return mixed
     */
    public function GetPageBlockData()
    {
        $id = $this->pageBlockData["id"];
        if (!empty($id)) {

            if (empty($this->emailManager)) {
                $this->emailManager = $this->container->get("email_manager");
            }

            $emails = array();

            $entity = $this->pageBlockData["model"]["entity"];
            if (!empty($entity)) {

                if ($entity->getEntityType()->getEntityTypeCode() != "email") {
                    $entity = $entity->getEmail();
                }

                if (!empty($entity)) {
                    $emails = $this->emailManager->parseCorrespondence($entity);
                }
            }

            $this->pageBlockData["model"]["emails"] = $emails;
        }

        return $this->pageBlockData;
    }

    /**
     * @return bool
     */
    public function isVisible()
    {
        return !empty($this->pageBlockData["id"]);
    }

    /**
     * @return string
     */
    public function GetPageBlockSetingsTemplate()
    {
        return "SharedInboxBusinessBundle:BlockSettings:email_view.html.twig";
    }

    /**
     * @return array
     */
    public function GetPageBlockSetingsData()
    {
        return array(
            "entity" => $this->pageBlock
        );
    }

    /**
     * @param $data
     * @return JsonResponse|PageBlock|bool
     */
    public function SavePageBlockSettings($data)
    {
        /** @var BlockManager $blockManager */
        $blockManager = $this->container->get("block_manager");

        $this->pageBlock->setTitle($data["title"]);
        $this->pageBlock->setClass($data["class"]);

        return $blockManager->save($this->pageBlock);
    }
}