<?php

namespace AppBundle\Managers;

use AppBundle\Abstracts\AbstractBaseManager;
use AppBundle\Context\AttributeContext;
use AppBundle\Context\AttributeGroupContext;
use AppBundle\Context\AttributeSetContext;
use AppBundle\Context\EntityTypeContext;
use AppBundle\Context\ListViewContext;
use AppBundle\Context\NavigationLinkContext;
use AppBundle\Context\PageBlockContext;
use AppBundle\Context\PageContext;
use AppBundle\Entity\Attribute;
use AppBundle\Entity\AttributeGroup;
use AppBundle\Entity\AttributeSet;
use AppBundle\Entity\CompositeFilter;
use AppBundle\Entity\CompositeFilterCollection;
use AppBundle\Entity\EntityType;
use AppBundle\Entity\ListView;
use AppBundle\Entity\ListViewAttribute;
use AppBundle\Entity\NavigationLink;
use AppBundle\Entity\Page;
use AppBundle\Entity\PageBlock;
use AppBundle\Entity\SearchFilter;
use IntegrationBusinessBundle\Managers\ChatgptApiManager;

class TranslationManager extends AbstractBaseManager
{

    /** @var EntityManager $entityManager */
    protected $entityManager;
    /** @var ChatgptApiManager $chatgptApiManager */
    protected $chatgptApiManager;

    public function initialize()
    {
        parent::initialize();
        $this->entityManager = $this->container->get("entity_manager");
    }

    /**
     * @param $id
     * @return |null
     */
    public function getCoreLanguageById($id)
    {

        $entityType = $this->entityManager->getEntityTypeByCode("core_language");

        return $this->entityManager->getEntityByEntityTypeAndId($entityType, $id);
    }

    /**
     * @param $bundle
     * @param $langCode
     * @return bool
     * @throws \Exception
     */
    public function createTranslationForBundle($bundle, $langCode)
    {

        $strings = $this->getStrings($bundle);

        $baseTranslationPath = $this->container->get('kernel')->getRootDir()."/../src/".$bundle."/Resources/translations/";

        if (!file_exists($baseTranslationPath)) {
            mkdir($baseTranslationPath, 0777, true);
        }

        $translationFilepath = $baseTranslationPath."messages.{$langCode}.yml";

        if (!file_exists($baseTranslationPath."messages.{$langCode}.yml")) {
            fopen($translationFilepath, "w");
        } else {
            if (file_exists($baseTranslationPath."messages.{$langCode}_bkp.yml")) {
                unlink($baseTranslationPath."messages.{$langCode}_bkp.yml");
            }
            copy($baseTranslationPath."messages.{$langCode}.yml", $baseTranslationPath."messages.{$langCode}_bkp.yml");
        }

        /**
         * Remove strings allready existing in app bundle
         */
        if ($bundle != "AppBundle" && !empty($strings)) {
            $baseTranslationPathAppBundle = $this->container->get('kernel')->getRootDir()."/../src/AppBundle/Resources/translations/";
            $translationFilepathAppBundle = $baseTranslationPathAppBundle."messages.{$langCode}.yml";
            if (!file_exists($translationFilepathAppBundle)) {
                throw new \Exception('Before this bundle please generate translation '.$langCode.' for AppBundle and other core bundles');
            }
            $existingTranslationsTmpAppBundle = file($translationFilepathAppBundle, FILE_IGNORE_NEW_LINES);
            foreach ($existingTranslationsTmpAppBundle as $existingTranslationTmpAppBundle) {
                if (!empty($existingTranslationTmpAppBundle) && strpos($existingTranslationTmpAppBundle, ': ') !== false) {
                    $existingTranslationTmpAppBundle = explode(": ", $existingTranslationTmpAppBundle);
                    $tmp = "";
                    if (isset($existingTranslationTmpAppBundle[1])) {
                        $tmp = $existingTranslationTmpAppBundle[1];
                    }
                    $existingTranslationsAppBundle[$existingTranslationTmpAppBundle[0]] = $tmp;

                    foreach ($strings as $key2 => $value) {
                        if (array_key_exists($value, $existingTranslationsAppBundle)) {
                            unset($strings[$key2]);
                        }
                    }
                }
            }
        }

        $existingTranslations = array();

        $existingTranslationsTmp = file($baseTranslationPath."messages.{$langCode}.yml", FILE_IGNORE_NEW_LINES);
        if (!empty($existingTranslationsTmp)) {
            foreach ($existingTranslationsTmp as $existingTranslationTmp) {
                if (!empty($existingTranslationTmp) && strpos($existingTranslationTmp, ': ') !== false) {
                    $existingTranslationTmp = explode(": ", $existingTranslationTmp);
                    $tmp = "";
                    if (isset($existingTranslationTmp[1])) {
                        $tmp = $existingTranslationTmp[1];
                    }
                    $existingTranslations[$existingTranslationTmp[0]] = $tmp;
                }
            }
        }

        $newTranslations = array();

        if (!empty($existingTranslations)) {
            foreach ($existingTranslations as $key => $trans) {
                if (!in_array($key, $strings)) {
                    unset($existingTranslations[$key]);
                }
            }

            foreach ($strings as $key2 => $value) {
                if (array_key_exists($value, $existingTranslations)) {
                    unset($strings[$key2]);
                }
            }

            foreach ($existingTranslations as $key3 => $trans) {
                $newTranslations[] = implode(": ", array($key3,$trans));
            }
        }


        if (!empty($strings)) {
            foreach ($strings as $string) {
                $newTranslations[] = $string.": ";
            }
        }

        if (!empty($newTranslations)) {
            $fp = fopen($baseTranslationPath."messages.{$langCode}.yml", 'w');
            foreach ($newTranslations as $row) {
                fwrite($fp, $row.PHP_EOL);
            }
            fclose($fp);
        }

        return true;
    }

    /**
     * @param $bundle
     * @return array
     * @throws \Exception
     */
    public function getStrings($bundle)
    {

        $strings = $this->getStringsFromFiles($bundle);

        /** @var EntityTypeContext $entityTypeContext */
        $entityTypeContext = $this->container->get("entity_type_context");

        $entityTypes = $entityTypeContext->getBy(array("bundle" => $bundle));

        /** @var AttributeContext $attributeContext */
        $attributeContext = $this->container->get("attribute_context");

        /** @var ListViewContext $listViewContext */
        $listViewContext = $this->container->get("list_view_context");

        /** @var AttributeSetContext $attributeSetContext */
        $attributeSetContext = $this->container->get("attribute_set_context");

        /** @var AttributeGroupContext $attributeGroupContext */
        $attributeGroupContext = $this->container->get("attribute_group_context");

        /** @var PageBlockContext $pageBlockContext */
        $pageBlockContext = $this->container->get("page_block_context");

        /** @var PageContext $pageContext */
        $pageContext = $this->container->get("page_context");

        /** @var NavigationLinkContext $navigationLinkContext */
        $navigationLinkContext = $this->container->get("navigation_link_context");

        if (!empty($entityTypes)) {

            /** @var EntityType $entityType */
            foreach ($entityTypes as $entityType) {

                /**
                 * Get attributes
                 */
                $attributes = $attributeContext->getAttributesByEntityType($entityType);

                if (!empty($attributes)) {

                    /** @var Attribute $attribute */
                    foreach ($attributes as $attribute) {
                        $strings[] = $attribute->getFrontendLabel();
                        if (!empty($attribute->getNote())) {
                            $strings[] = $attribute->getNote();
                        }
                        if (!empty($attribute->getValidator())) {
                            $validators = json_decode($attribute->getValidator());

                            if (!empty($validators) && is_array($validators)) {
                                foreach ($validators as $validator) {
                                    if (isset($validator->message)) {
                                        $strings[] = $validator->message;
                                    }
                                }
                            } else {
                                dump($validators);
                            }
                        }
                    }
                }

                /**
                 * Get list views
                 */
                $listViews = $listViewContext->getListViewsByEntityType($entityType);

                if (!empty($listViews)) {

                    /** @var ListView $listView */
                    foreach ($listViews as $listView) {
                        $strings[] = $listView->getDisplayName();

                        $listViewAttributes = $listView->getListViewAttributes();

                        if (!empty($listViewAttributes)) {

                            /** @var ListViewAttribute $listViewAttribute */
                            foreach ($listViewAttributes as $listViewAttribute) {
                                $strings[] = $listViewAttribute->getLabel();
                            }
                        }
                    }
                }

                /**
                 * Pages
                 */
                $pages = $pageContext->getOneBy(array("entityType" => $entityType));

                if (!empty($pages)) {

                    /** @var Page $page */
                    foreach ($pages as $page) {
                        $strings[] = $page->getTitle();
                    }
                }

                /**
                 * Page blocks
                 */
                $pageBlocks = $pageBlockContext->getOneBy(array("entityType" => $entityType));

                if (!empty($pageBlocks)) {

                    /** @var PageBlock $pageBlock */
                    foreach ($pageBlocks as $pageBlock) {
                        $strings[] = $pageBlock->getTitle();
                    }
                }

                /**
                 * Attribute sets
                 */
                $attributeSets = $attributeSetContext->getAttributeSetsByEntityType($entityType);

                if (!empty($attributeSets)) {

                    /** @var AttributeSet $attributeSet */
                    foreach ($attributeSets as $attributeSet) {
                        /**
                         * Attribute groups
                         */
                        $attributeGroups = $attributeGroupContext->getBy(array("attributeSet" => $attributeSet));

                        if (!empty($attributeGroups)) {

                            /** @var AttributeGroup $attributeGroup */
                            foreach ($attributeGroups as $attributeGroup) {
                                $strings[] = $attributeGroup->getAttributeGroupName();
                            }
                        }
                    }
                }
            }
        }

        /**
         * Navigation
         */
        $navigationLinks = $navigationLinkContext->getBy(array("bundle" => $bundle));

        if (!empty($navigationLinks)) {

            /** @var NavigationLink $navigationLink */
            foreach ($navigationLinks as $navigationLink) {
                $strings[] = $navigationLink->getDisplayName();
            }
        }

        $strings = array_unique($strings);

        foreach ($strings as $key => $string) {
            $strings[$key] = preg_replace('/\:/', '\\\\$0', $string);
        }

        return $strings;
    }

    /**
     * @param $bundle
     * @return array
     * @throws \Exception
     */
    public function getStringsFromFiles($bundle)
    {

        $strings = array();

        $baseBundlePath = $this->container->get('kernel')->getRootDir()."/../src/".$bundle;

        if (!file_exists($baseBundlePath)) {
            throw new \Exception($bundle.' does not exist');
        }

        $files = array();

        $this->getDirContents($baseBundlePath, $files);

        foreach ($files as $file) {
            $stringsTmp = $this->getStringsFromFile($file);
            if (!empty($stringsTmp)) {
                $strings = array_merge($strings, $stringsTmp);
            }
        }

        if ($bundle == "AppBundle") {
            $files = array();
            $baseBundlePath = $this->container->get('kernel')->getRootDir()."/Resources";

            $this->getDirContents($baseBundlePath, $files);

            foreach ($files as $file) {
                $stringsTmp = $this->getStringsFromFile($file);
                if (!empty($stringsTmp)) {
                    $strings = array_merge($strings, $stringsTmp);
                }
            }
        }

        foreach ($strings as $key => $string) {
            $strings[$key] = preg_replace("/\r|\n/", "", $string);
            if (empty($string)) {
                unset($strings[$key]);
            }
        }

        $strings = array_unique($strings);

        return $strings;
    }

    /**
     * @param $dir
     * @param array $results
     * @return array
     */
    function getDirContents($dir, &$results = array())
    {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            if (!is_dir($path)) {
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                if (!in_array($ext, array("php","twig"))) {
                    continue;
                }
                $results[] = $path;
            } elseif ($value != "." && $value != "..") {
                if ($this->contains($path, array("controller","manager","command","resources"))) {
                    $this->getDirContents($path, $results);
                    //$results[] = $path;
                }
            }
        }

        return $results;
    }

    /**
     * @param $str
     * @param array $arr
     * @return bool
     */
    function contains($str, array $arr)
    {
        foreach ($arr as $a) {
            if (stripos(strtolower($str), $a) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $path
     * @return array|mixed
     */
    public function getStringsFromFile($path)
    {

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        $content = file_get_contents($path);

        if ($ext == "php") {
            $strings_tmp = array();
            preg_match_all('/\$this->translator->trans\(\'(.*?)\'\)/s', $content, $strings);
            if (isset($strings[1])) {
                $strings_tmp = array_merge($strings_tmp, $strings[1]);
            }
            preg_match_all('/\$this->translator->trans\(\"(.*?)\"\)/s', $content, $strings);
            if (isset($strings[1])) {
                $strings_tmp = array_merge($strings_tmp, $strings[1]);
            }
            return $strings_tmp;
        } elseif ($ext == "twig") {
            preg_match_all('/\{\% trans \%\}(.*?)\{\% endtrans \%\}/s', $content, $strings);
            if (isset($strings[1])) {
                return $strings[1];
            }
        }

        return array();
    }

    /**
     * @param $langCode
     * @return mixed
     */
    public function checkIfLangCodeExists($langCode)
    {

        $coreLanguageEntityType = $this->entityManager->getEntityTypeByCode("core_language");

        $compositeFilter = new CompositeFilter();
        $compositeFilter->setConnector("and");
        $compositeFilter->addFilter(new SearchFilter("entityStateId", "eq", 1));
        $compositeFilter->addFilter(new SearchFilter("code", "eq", $langCode));

        $compositeFilters = new CompositeFilterCollection();
        $compositeFilters->addCompositeFilter($compositeFilter);

        return $this->entityManager->getEntitiesByEntityTypeAndFilter($coreLanguageEntityType, $compositeFilters);
    }

    /**
     * @param $bundle
     * @param $langCode
     * @return bool
     */
    public function translateBundle($bundle, $langCode)
    {
        if (empty($this->chatgptRestManager)){
            $this->chatgptRestManager = new RestManager();
        }

        $data = Array();
        $data["model"] = "text-davinci-003";
        $data["temperature"] = 0;
        $data["max_tokens"] = 2000;
        $data["top_p"] = 1;
        $data["frequency_penalty"] = 0;
        $data["presence_penalty"] = 0;

        if(empty($this->chatgptApiManager)){
            $this->chatgptApiManager = $this->container->get("chatgpt_api_manager");
        }

        $newTranslations = array();
        $existingTranslations = array();

        /** @var HelperManager $helperManager */
        //$helperManager = $this->container->get("helper_manager");

        $baseTranslationPath = $this->container->get('kernel')->getRootDir()."/../src/".$bundle."/Resources/translations/";

        $translationFilepath = $baseTranslationPath."messages.{$langCode}.yml";

        if (!file_exists($baseTranslationPath."messages.{$langCode}.yml")) {
            fopen($translationFilepath, "w");
        }

        $existingDestionationTranslations = Array();
        $destinationTranslationsTmp = file($baseTranslationPath."messages.{$langCode}.yml", FILE_IGNORE_NEW_LINES);
        if (!empty($destinationTranslationsTmp)) {
            foreach ($destinationTranslationsTmp as $destinationTranslationTmp) {
                if (!empty($destinationTranslationTmp) && strpos($destinationTranslationTmp, ': ') !== false) {
                    $destinationTranslationTmp = explode(": ", $destinationTranslationTmp);
                    $existingDestionationTranslations[$destinationTranslationTmp[0]] = $destinationTranslationTmp[1];
                }
            }
        }

        $i = 10;
        $existingTranslationsTmp = file($baseTranslationPath."messages.hr.yml", FILE_IGNORE_NEW_LINES);
        if (!empty($existingTranslationsTmp)) {
            foreach ($existingTranslationsTmp as $existingTranslationTmp) {
                if (!empty($existingTranslationTmp) && strpos($existingTranslationTmp, ': ') !== false) {
                    $existingTranslationTmp = explode(": ", $existingTranslationTmp);
                    if(!isset($existingTranslationTmp[1]) || empty($existingTranslationTmp[1])){
                        continue;
                    }
                    if(isset($existingDestionationTranslations[$existingTranslationTmp[0]])){
                        continue;
                    }
                    $tmp = "";
                    /*if (isset($existingTranslationTmp[1])) {
                        $tmp = $existingTranslationTmp[1];
                    }*/

                    $data["prompt"] = "Please translate string '{$existingTranslationTmp[0]}' from language en to language sr";

                    dump($data["prompt"]);

                    if (empty($tmp)) {
                        $this->chatgptRestManager = new RestManager();
                        try{
                            $res = $this->chatgptApiManager->getApiData($this->chatgptRestManager,"completions",$data);
                        }
                        catch (\Exception $e){
                            dump($e->getMessage());
                            continue;
                        }

                        if(isset($res["choices"][0]["text"])){
                            $tmp = trim($res["choices"][0]["text"]);
                            $tmp = rtrim($tmp,'.');
                        }
                        //$tmp = $helperManager->translateString($existingTranslationTmp[0], "en", $langCode);
                    }

                    $existingTranslations[$existingTranslationTmp[0]] = $tmp;
                }
            }
        } else {
            return false;
        }

        foreach ($existingTranslations as $key3 => $trans) {
            $newTranslations[] = implode(": ", array($key3,$trans));
        }

        if (!empty($newTranslations)) {
            $fp = fopen($baseTranslationPath."messages.{$langCode}.yml", 'a');
            foreach ($newTranslations as $row) {
                fwrite($fp, $row.PHP_EOL);
            }
            fclose($fp);
        }

        return true;
    }
}
