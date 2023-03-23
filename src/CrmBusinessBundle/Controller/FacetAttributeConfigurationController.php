<?php

namespace CrmBusinessBundle\Controller;

use AppBundle\Entity\SettingsEntity;
use AppBundle\Managers\ApplicationSettingsManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use AppBundle\Abstracts\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class FacetAttributeConfigurationController extends AbstractController
{
    /** @var ApplicationSettingsManager $applicationSettingsManager */
    private $applicationSettingsManager;

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * @Route("/core/facet_attribute_configuration/save_items", name="facet_attribute_configuration_save_items")
     * @Method("POST")
     * @param Request $request
     * @return Response
     */
    public function facetAttributeConfigurationSaveAction(Request $request)
    {
        $p = $_POST;

        $this->initialize();

        if (empty($p)) {
            return new JsonResponse(array('error' => true, 'title' => $this->translator->trans('Error'), 'message' => $this->translator->trans('Missing data')));
        }

        if (empty($this->applicationSettingsManager)) {
            $this->applicationSettingsManager = $this->getContainer()->get("application_settings_manager");
        }
        $key = 'facet_attribute_configuration';

        /** @var SettingsEntity $setting */
        $setting = $this->applicationSettingsManager->getRawApplicationSettingEntityByCode($key);
        if (empty($setting)) {
            $setting = $this->applicationSettingsManager->addApplicationSetting("Facet attribute configuration", $key);
        }

        $value = json_encode($p);
        $this->applicationSettingsManager->setApplicationSettingValue($setting, $value);

        $this->cacheManager->setCacheItem("facet_attribute_configuration", $value, ["facet_attribute_configuration"]);

        return new JsonResponse(array('error' => false, 'title' => $this->translator->trans('Success'), 'message' => $this->translator->trans('Facet attribute configuration saved')));
    }
}