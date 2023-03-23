<?php

namespace AppBundle\Interfaces\Blocks;

/**
 * An interface that every block should implement
 */
interface BlockInterface
{
    /** Return name of the TWIG template used by the page block*/
    public function GetPageBlockTemplate();

    /**Return data that will be used in page block Twig template*/
    public function GetPageBlockData();

    /** Return name of the TWIG template used by the page block settings*/
    /**TODO : Add to interface after development is done*/
   /*public function GetSettingsPageBlockTemplate();*/
}
