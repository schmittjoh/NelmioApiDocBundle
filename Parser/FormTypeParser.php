<?php

/*
 * This file is part of the NelmioApiDocBundle.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Nelmio\ApiDocBundle\Parser;

use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Exception\FormException;

class FormTypeParser implements ParserInterface
{
    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var array
     */
    protected $mapTypes = array(
        'text'      => 'string',
        'date'      => 'date',
        'datetime'  => 'datetime',
        'checkbox'  => 'boolean',
        'time'      => 'time',
        'number'    => 'float',
        'integer'   => 'int',
        'textarea'  => 'string',
        'country'   => 'string',
    );

    public function __construct(FormFactoryInterface $formFactory, FormRegistry $formRegistry = null)
    {
        $this->formFactory  = $formFactory;
        $this->formRegistry = $formRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($item)
    {
        try {
            if ($this->createForm($item)) {
                return true;
            }
        } catch (FormException $e) {
            return false;
        } catch (MissingOptionsException $e) {
            return false;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function parse($type)
    {
        if ($this->implementsType($type)) {
            $type = $this->getTypeInstance($type);
        }

        $form = $this->formFactory->createBuilder($type);

        return $this->parseForm($form);
    }

    private function parseForm($form, $prefix = null)
    {
        $parameters = array();
        foreach ($form->all() as $name => $child) {
            if ($child instanceof FormBuilder) {
                $childBuilder = $child;
            } else {
                $childBuilder = $form->create($name, $child['type'] ?: 'text', $child['options']);
            }

            $bestType = '';
            foreach ($childBuilder->getTypes() as $type) {
                if (isset($this->mapTypes[$type->getName()])) {
                    $bestType = $this->mapTypes[$type->getName()];
                }
            }

            $parameters[$name] = array(
                'dataType' => $bestType,
                'required' => $childBuilder->getRequired(),
                'description' => $childBuilder->getAttribute('description'),
                'readonly' => $childBuilder->getDisabled(),
            );
        }

        return $parameters;
    }

    private function implementsType($item)
    {
        if (!class_exists($item)) {
            return false;
        }
        $refl = new \ReflectionClass($item);

        return $refl->implementsInterface('Symfony\Component\Form\FormTypeInterface');
    }

    private function getTypeInstance($type)
    {
        return unserialize(sprintf('O:%d:"%s":0:{}', strlen($type), $type));
    }

    private function createForm($item)
    {
        return $this->formFactory->create($item);
    }
}
