<?php

namespace AppBundle\Interfaces\Managers;

interface AutocompleteManagerInterface
{
    public function getAutoComplete($term, $attribute, $formData);

    public function renderTemplate($data, $template, $request);

    public function renderSingleItem($item, $attributeCode);

    public function getRenderedItemById($attribute, $id);
}
