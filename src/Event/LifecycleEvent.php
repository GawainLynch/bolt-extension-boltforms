<?php

namespace Bolt\Extension\Bolt\BoltForms\Event;

use Bolt\Extension\Bolt\BoltForms\Config\FormConfig;
use Bolt\Extension\Bolt\BoltForms\Config\MetaData;
use Bolt\Extension\Bolt\BoltForms\FormData;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Form\Button;

/**
 * BoltForms submission lifecycle event.
 *
 * Copyright (c) 2014-2016 Gawain Lynch
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Gawain Lynch <gawain.lynch@gmail.com>
 * @copyright Copyright (c) 2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */
class LifecycleEvent extends Event
{
    /** @var FormConfig $formConfig */
    protected $formConfig;
    /** @var FormData $formData */
    protected $formData;
    /** @var MetaData */
    private $formMetaData;
    /** @var Button */
    protected $clickedButton;

    /**
     * Constructor.
     *
     * @param FormConfig $formConfig
     * @param FormData   $formData
     * @param MetaData   $formMetaData
     * @param Button     $clickedButton
     */
    public function __construct(
        FormConfig $formConfig,
        FormData $formData,
        MetaData $formMetaData,
        Button $clickedButton
    ) {
        $this->formConfig = $formConfig;
        $this->formData = $formData;
        $this->formMetaData = $formMetaData;
        $this->clickedButton = $clickedButton;
    }

    /**
     * @return FormConfig
     */
    public function getFormConfig()
    {
        return $this->formConfig;
    }

    /**
     * @return FormData
     */
    public function getFormData()
    {
        return $this->formData;
    }

    /**
     * @return MetaData
     */
    public function getFormMetaData()
    {
        return $this->formMetaData;
    }

    /**
     * @return Button
     */
    public function getClickedButton()
    {
        return $this->clickedButton;
    }
}
