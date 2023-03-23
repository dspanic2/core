<?php

namespace AppBundle\Models;


class MailSender
{
    /**@var \AppBundle\Models\MailAddress $form*/
    private $from;
    /**@var \AppBundle\Models\MailAddress $replayTo*/
    private $replayTo;
    /**@var \AppBundle\Models\MailAddress $noReplay*/
    private $noReplay;

    /**
     * @return MailAddress
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param MailAddress $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @return MailAddress
     */
    public function getReplayTo()
    {
        return $this->replayTo;
    }

    /**
     * @param MailAddress $replayTo
     */
    public function setReplayTo($replayTo)
    {
        $this->replayTo = $replayTo;
    }

    /**
     * @return MailAddress
     */
    public function getNoReplay()
    {
        return $this->noReplay;
    }

    /**
     * @param MailAddress $noReplay
     */
    public function setNoReplay($noReplay)
    {
        $this->noReplay = $noReplay;
    }




}