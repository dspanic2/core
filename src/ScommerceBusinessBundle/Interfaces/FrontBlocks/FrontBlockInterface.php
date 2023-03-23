<?php

namespace ScommerceBusinessBundle\Interfaces\FrontBlocks;

/**
 * An interface that every block should implement
 */
interface FrontBlockInterface
{
    /**Return data that will be used in page block Twig template*/
    public function GetBlockData();

    /** Return name of the TWIG template used by the page block settings*/
    /**TODO : Add to interface after development is done*/
    /*public function GetSettingsPageBlockTemplate();*/
}
