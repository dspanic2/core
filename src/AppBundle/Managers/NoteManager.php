<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\CoreUserEntity;
use AppBundle\Entity\NoteEntity;
use AppBundle\Entity\NoteUserLikeEntity;
use AppBundle\Entity\SearchFilter;
use AppBundle\Helpers\EntityHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class NoteManager extends AbstractBaseManager
{
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var HelperManager $helperManager */
    protected $helperManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
        $this->helperManager = $this->container->get("helper_manager");
    }

    /**
     * @param $data
     * @param NoteEntity|null $note
     * @param $skipLog
     * @return NoteEntity|null
     */
    public function insertUpdateNote($data, NoteEntity $note = null, $skipLog = true){

        if (empty($note)) {
            /** @var NoteEntity $note */
            $note = $this->entityManager->getNewEntityByAttributSetName("note");
        }

        foreach ($data as $key => $value) {
            $setter = EntityHelper::makeSetter($key);

            if (EntityHelper::checkIfMethodExists($note, $setter)) {
                $note->$setter($value);
            }
        }

        if ($skipLog) {
            $this->entityManager->saveEntityWithoutLog($note);
        } else {
            $this->entityManager->saveEntity($note);
        }
        $this->entityManager->refreshEntity($note);

        return $note;
    }

    /**
     * @param $relatedEntityType
     * @param $relatedEntityId
     * @return mixed
     */
    public function getNotesForEntity($relatedEntityType, $relatedEntityId)
    {
        $etNote = $this->entityManager->getEntityTypeByCode("note");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("relatedEntityType", "eq", $relatedEntityType));
        $compositeFilter->addFilter(new SearchFilter("relatedEntityId", "eq", $relatedEntityId));
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($etNote, $compositeFilters);
    }

    /**
     * Toggle like for note and return new array of user names
     * @param $noteId
     * @return array
     */
    public function toggleLike($noteId)
    {
        $noteUserLikeNames = Array();

        $etNote = $this->entityManager->getEntityTypeByCode("note");

        /** @var NoteEntity $noteEntity */
        $noteEntity = $this->entityManager->getEntityByEntityTypeAndId($etNote, $noteId);
        if (!empty($noteEntity)) {
            $likeFound = false;

            /** @var CoreUserEntity $currentUser */
            $currentUser = $this->helperManager->getCurrentCoreUser();

            $noteUserLikeEntities = $noteEntity->getUserLikes();
            if (!empty($noteUserLikeEntities)) {
                /** @var NoteUserLikeEntity $noteUserLikeEntity */
                foreach ($noteUserLikeEntities as $noteUserLikeEntity) {
                    // found like from current user, remove it
                    if ($noteUserLikeEntity->getUserId() == $currentUser->getId()) {
                        $this->entityManager->deleteEntityFromDatabase($noteUserLikeEntity);
                        $likeFound = true;
                    } else {
                        /** @var CoreUserEntity $coreUser */
                        $coreUser = $noteUserLikeEntity->getUser();
                        if (!empty($coreUser)) {
                            $noteUserLikeNames[] = $coreUser->getUsername();
                        }
                    }
                }

                if (!$likeFound) {
                    // no like from current user, add it
                    $noteUserLikeEntity = $this->entityManager->getNewEntityByAttributSetName("note_user_like");
                    $noteUserLikeEntity->setUser($currentUser);
                    $noteUserLikeEntity->setNote($noteEntity);
                    $this->entityManager->saveEntity($noteUserLikeEntity);

                    $noteUserLikeNames[] = $currentUser->getUsername();
                }
            }
        }

        return $noteUserLikeNames;
    }

    /**
     * @param $noteId
     */
    public function deleteLikesForNote($noteId)
    {
        $etNote = $this->entityManager->getEntityTypeByCode("note");

        /** @var NoteEntity $noteEntity */
        $noteEntity = $this->entityManager->getEntityByEntityTypeAndId($etNote, $noteId);
        if (!empty($noteEntity)) {
            $noteUserLikeEntities = $noteEntity->getUserLikes();
            if (!empty($noteUserLikeEntities)) {
                /** @var NoteUserLikeEntity $noteUserLikeEntity */
                foreach ($noteUserLikeEntities as $noteUserLikeEntity) {
                    $this->entityManager->deleteEntityFromDatabase($noteUserLikeEntity);
                }
            }
        }
    }
}