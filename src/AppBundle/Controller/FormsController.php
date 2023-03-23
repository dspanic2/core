<?php

namespace AppBundle\Controller;

use AppBundle\Abstracts\AbstractController;
use AppBundle\Context\EntityAttributeContext;
use AppBundle\Entity;
use AppBundle\Factory\FactoryEntityType;
use AppBundle\Interfaces\Entity\IEntityValidation;
use AppBundle\Managers\EntityManager;
use AppBundle\Managers\FormManager;
use CrmBusinessBundle\Entity\AccountEntity;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bridge\Twig\TwigEngine;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\VarDumper\VarDumper;

class FormsController extends AbstractController
{
    /** @var FormManager $formManager */
    protected $formManager;
    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var EntityAttributeContext $entityAttributeContext */
    protected $entityAttributeContext;

    protected function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->getContainer()->get('entity_manager');
    }

    protected function initializeType($type)
    {
        $factoryManager = $this->getContainer()->get('factory_manager');
        $this->formManager = $factoryManager->loadFormManager($type);
    }

    /**
     * @Route("/form/field", name="form_field")
     */
    public function fieldAction(Request $request)
    {
        $entityAttribute = $request->get('entityAttribute');
        $entity = $request->get('entity');
        $formType = $request->get('formType');
        $parent = $request->get('parent');

        $field = $this->getContainer()->get($entityAttribute->getAttribute()->getFrontendType() . '_field');

        $field->SetAttribute($entityAttribute->getAttribute());
        $field->SetEntity($entity);
        $field->SetFormType($formType);
        $field->SetParent($parent);

        return new Response($field->GetFormFieldHtml());
    }

    /**
     * @Route("/form/advancedSearchField", name="advanced_search_field")
     */
    public function advancedSearchFieldAction(Request $request)
    {
        /** @var Entity\Attribute $attribute */
        $attribute = $request->get('attribute');
        $value = $request->get('value');
        $search_type = $request->get('search_type');

        $field = $this->getContainer()->get($attribute->getFrontendType() . '_field');

        $field->SetAttribute($attribute);
        $field->SetAdvancedSearchValue($value);
        $field->SetAdvancedSearchType($search_type);

        return new Response($field->GetAdvancedSearchFieldHtml());
    }

    /**
     * @Route("/form/quickSearchField", name="quick_search_field")
     */
    public function quickSearchFieldAction(Request $request)
    {
        /** @var Entity\Attribute $attribute */
        $attribute = $request->get('attribute');
        $value = $request->get('value');

        $field = $this->getContainer()->get($attribute->getFrontendType() . '_field');
        $field->SetAttribute($attribute);
        $field->SetQuickSearchValue($value);

        return new Response($field->GetQuickSearchFieldHtml());
    }

    /**
     * @Route("/{type}/delete/{id}", name="delete")
     */
    public function deleteAction($type, $id = null)
    {
        $this->initialize();
        $this->initializeType($type);

        /** @var Entity\Entity $entity */
        $entity = $this->formManager->deleteFormModel($type, $id);

        if ($entity->getEntityValidationCollection() != null) {
            /**@var IEntityValidation $firstValidation */
            $firstValidation = $entity->getEntityValidationCollection()[0];

            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans($firstValidation->getTitle()),
                    'message' => $this->translator->trans($firstValidation->getMessage()),
                )
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Delete item'),
                'message' => $this->translator->trans('Item successfully deleted'),
            )
        );
    }

    /**
     * @Route("/{type}/save", name="save_form")
     * @Method("POST")
     */
    public function saveAction($type, Request $request)
    {
        $this->initialize();
        $this->initializeType($type);

        if(isset($_POST["multiselect"])){
            foreach ($_POST["multiselect"] as $key => $multiselect){
                if(isset($multiselect["related_ids"]) && is_string($multiselect["related_ids"])){
                    $_POST["multiselect"][$key]["related_ids"] = Array();
                    $_POST["multiselect"][$key]["related_ids"][] = $multiselect["related_ids"];
                }
            }

        }

        $p = $_POST;

        $entityValidate = $this->formManager->validateFormModel($type, $p);
        if ($entityValidate->getIsValid() == false) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error occurred'),
                    'message' => $entityValidate->getMessage()
                )
            );
        }

        /** @var Entity\Entity $entity */
        $entity = $this->formManager->saveFormModel($type, $p);
        if (empty($entity)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error occurred'),
                    'message' => $this->translator->trans('There has been an error')
                )
            );
        }

        if ($entity->getEntityValidationCollection() != null) {
            /**@var IEntityValidation $firstValidation */
            $firstValidation = $entity->getEntityValidationCollection()[0];

            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans($firstValidation->getTitle()),
                    'message' => $this->translator->trans($firstValidation->getMessage())
                )
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Success'),
                'message' => $this->translator->trans('Form has been submitted'),
                'entity' => $this->entityManager->entityToArray($entity)
            )
        );
    }

    /**
     * @Route("/{type}/clone", name="clone")
     * @Method("POST")
     */
    public function cloneAction($type, Request $request)
    {
        $this->initialize();
        $this->initializeType($type);

        /** @var Entity\AttributeSet $attributeSet */
        $attributeSet = $this->entityManager->getAttributeSetByCode($type);

        /** @var AccountEntity $entity */
        $entity = $this->entityManager->getEntityByEntityTypeAndId($attributeSet->getEntityType(), $_POST["id"]);
        if (empty($entity)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error cloning entity'),
                    'message' => $this->translator->trans('Entity does not exist'),
                )
            );
        }

        $newEntity = $this->entityManager->cloneEntity(
            $entity,
            $entity->getAttributeSet()->getAttributeSetCode(),
            null,
            true
        );

        if (empty($newEntity)) {
            return new JsonResponse(
                array(
                    'error' => true,
                    'title' => $this->translator->trans('Error cloning entity'),
                    'message' => $this->translator->trans('There has been an error'),
                )
            );
        }

        return new JsonResponse(
            array(
                'error' => false,
                'title' => $this->translator->trans('Success'),
                'message' => $this->translator->trans('Entity has been cloned'),
                'entity' => $this->entityManager->entityToArray($newEntity),
            )
        );
    }
}
