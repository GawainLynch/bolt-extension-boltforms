<?php

namespace Bolt\Extension\Bolt\BoltForms\Twig;

use Bolt\Extension\Bolt\BoltForms\BoltForms;
use Bolt\Extension\Bolt\BoltForms\Config\Config;
use Bolt\Extension\Bolt\BoltForms\Exception;
use Silex\Application;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Twig functions for BoltForms
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
 * @copyright Copyright (c) 2014-2016, Gawain Lynch
 * @license   http://opensource.org/licenses/GPL-3.0 GNU Public License 3.0
 */
class BoltFormsExtension
{
    /** @var Application */
    private $app;
    /** @var Config */
    private $config;

    public function __construct(Application $app, Config $config)
    {
        $this->app    = $app;
        $this->config = $config;
    }

    /**
     * Twig function for form generation.
     *
     * @param string $formName       Name of the BoltForm to render
     * @param string $htmlPreSubmit  HTML or template name to display BEFORE submit
     * @param string $htmlPostSubmit HTML or template name to display AFTER successful submit
     * @param array  $data           Data array passed to Symfony Forms
     * @param array  $options        Options array passed to Symfony Forms
     * @param array  $defaults       Default field values
     * @param array  $override       Array of form parameters / fields to override settings for
     * @param mixed  $meta           Meta data that is not transmitted with the form
     *
     * @return \Twig_Markup
     */
    public function twigBoltForms(
        $formName,
        $htmlPreSubmit = null,
        $htmlPostSubmit = null,
        $data = null,
        $options = [],
        $defaults = null,
        $override = null,
        $meta = null
    ) {
        if (!$this->config->getBaseForms()->has($formName)) {
            return new \Twig_Markup(
                "<p><strong>BoltForms is missing the configuration for the form named '$formName'!</strong></p>",
                'UTF-8'
            );
        }

        // If defaults are passed in, set them in data but don't override the
        // data array that might also be passed in
        if ($defaults !== null) {
            $options['data'] = $defaults;
        }

        // Set form runtime overrides
        if ($override !== null) {
            $this->config->addFormOverride($formName, $override);
        }

        /** @var Helper\FormHelper $formHelper */
        $formHelper = $this->app['boltforms.twig.helper']['form'];
        /** @var BoltForms $boltForms */
        $boltForms = $this->app['boltforms'];
        /** @var Session $session */
        $session = $this->app['session'];

        try {
            $boltForms
                ->create($formName, FormType::class, $data, $options)
                ->setMeta((array) $meta)
            ;
        } catch (Exception\BoltFormsException $e) {
            return $this->handleException($formName, $e);
        }

        // Get the form's configuration object
        $formConfig = $this->config->getForm($formName);

        // Get the form context compiler
        $formContext = $formHelper->getContextCompiler($formName);

        // Handle the POST
        $reCaptchaResponse = $this->app['recapture.response.factory']();
        try {
            $formHelper->handleFormRequest($formConfig, $formContext, $reCaptchaResponse);
        } catch (HttpException $e) {
            return null;
        }

        $loadAjax = $formConfig->getSubmission()->isAjax();
        $twig = $this->app['twig'];

        $formContext
            ->setAction($this->getRelevantAction($formName, $loadAjax))
            ->setHtmlPreSubmit($formHelper->getOptionalHtml($twig, $htmlPreSubmit))
            ->setHtmlPostSubmit($formHelper->getOptionalHtml($twig, $htmlPostSubmit))
            ->setReCaptchaResponse($reCaptchaResponse)
            ->setDefaults((array) $defaults)
        ;
        $session->set('boltforms_compiler_' . $formName, $formContext);

        return $formHelper->getFormRender($formName, $formConfig, $formContext, $loadAjax);
    }

    /**
     * Twig function to display uploaded files, downloadable via the controller.
     *
     * @param string $formName
     *
     * @return \Twig_Markup
     */
    public function twigBoltFormsUploads($formName = null)
    {
        $uploadConfig = $this->config->getUploads();
        $dir = realpath($uploadConfig->get('base_directory') . DIRECTORY_SEPARATOR . $formName);
        if ($dir === false) {
            return new \Twig_Markup('<p><strong>Invalid upload directory</strong></p>', 'UTF-8');
        }

        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->ignoreUnreadableDirs()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
        ;

        $context = [
            'directories' => $finder->directories(),
            'files'       => $finder->files(),
            'base_uri'    => '/' . $uploadConfig->get('base_uri') . '/download',
            'webpath'     => $this->app['extensions']->get('bolt/boltforms')->getWebDirectory()->getPath(),
        ];

        // Render the Twig
        $this->app['twig.loader.filesystem']->addPath(dirname(dirname(__DIR__)) . '/templates');
        $html = $this->app['twig']->render($this->config->getTemplates()->get('files'), $context);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Determine the form 'action' to be used.
     *
     * @param string $formName
     * @param bool   $loadAjax
     *
     * @return string
     */
    protected function getRelevantAction($formName, $loadAjax)
    {
        if ($loadAjax) {
            return $this->app['url_generator']->generate('boltFormsAsyncSubmit', ['form' => $formName]);
        }

        /** @var RequestStack $requestStack */
        $requestStack = $this->app['request_stack'];

        return $requestStack->getCurrentRequest()->getRequestUri();
    }

    /**
     * Handle an exception and render something user friendly.
     *
     * @param string                       $formName
     * @param Exception\BoltFormsException $e
     *
     * @return \Twig_Markup
     */
    protected function handleException($formName, Exception\BoltFormsException $e)
    {
        /** @var RequestStack $requestStack */
        $requestStack = $this->app['request_stack'];
        /** @var Helper\FormHelper $formHelper */
        $formHelper = $this->app['boltforms.twig.helper']['form'];
        /** @var FlashBag $feedback */
        $feedback = $this->app['boltforms.feedback'];

        /** @var \Exception $e */
        $feedback->add('debug', $this->getSafeTrace($e));
        $feedback->add('error', $e->getMessage());

        $requestStack->getCurrentRequest()->request->set($formName, true);
        $compiler = $formHelper->getContextCompiler($formName);
        $html = $formHelper->getExceptionRender($formName, $compiler, $this->app['twig']);

        return new \Twig_Markup($html, 'UTF-8');
    }

    /**
     * Remove the root path from the trace.
     *
     * @param \Exception $e
     *
     * @return string
     */
    protected function getSafeTrace(\Exception $e)
    {
        $rootDir = $this->app['resources']->getPath('root');
        $trace = explode("\n", $e->getTraceAsString());
        $trace = array_slice($trace, 0, 10);
        $trace = implode("\n", $trace);
        $trace = str_replace($rootDir, '{root}', $trace);
        $message = sprintf(
            "%s\n%s",
            $e->getMessage(),
            $trace
        );

        return $message;
    }
}
