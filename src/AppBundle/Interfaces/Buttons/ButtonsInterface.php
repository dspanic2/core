<?php

namespace AppBundle\Interfaces\Buttons;

/**
 * An interface that every block should implement
 */
interface ButtonsInterface
{
    /**
     * Returns buttons for form page
     * @return mixed
     */
    public function GetFormPageButtons();

    /**
     * Returns buttons for list view page
     * @return mixed
     */
    public function GetListPageButtons();

    /**
     * Returns buttons for modal form
     * @return mixed
     */
    public function GetModalFormPageButtons();

    /**
     * Returns buttons for dashboard page
     * @return mixed
     */
    public function GetDashboardPageButtons();

}
