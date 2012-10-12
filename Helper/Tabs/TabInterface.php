<?php

namespace Kunstmaan\NodeBundle\Tabs;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

interface TabInterface
{

    /**
     * @param FormBuilderInterface $builder The form builder
     */
    public function buildForm(FormBuilderInterface $builder);

    /**
     * @param Request $request The request
     */
    public function bindRequest(Request $request);

    /**
     * @param EntityManager $em      The entity manager
     */
    public function persist(EntityManager $em);

    /**
     * @param FormView $formView
     *
     * @return array
     */
    public function getFormErrors(FormView $formView);

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getIdentifier();

    /**
     * @param $identifier
     *
     * @return TabInterface
     */
    public function setIdentifier($identifier);

    /**
     * @return string
     */
    public function getTemplate();

}
