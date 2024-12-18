<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\StimulusBundle\Dto\StimulusAttributes;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FormTypeExtension extends AbstractTypeExtension
{
    private StimulusAttributes $stimulusAttributes;

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (
            null === $options['stimulus_controller']
            && null === $options['stimulus_target']
            && null === $options['stimulus_action']
        ) {
            return;
        }

        $this->stimulusAttributes = new StimulusAttributes(new Environment(new ArrayLoader()));

        if (true === \array_key_exists('stimulus_controller', $options)) {
            $this->handleController($options['stimulus_controller']);
        }

        if (true === \array_key_exists('stimulus_target', $options)) {
            $this->handleTarget($options['stimulus_target']);
        }

        if (true === \array_key_exists('stimulus_action', $options)) {
            $this->handleAction($options['stimulus_action']);
        }

        $attributes = array_merge($view->vars['attr'], $this->stimulusAttributes->toArray());

        $view->vars['attr'] = $attributes;
    }

    private function handleController(string|array $controllers): void
    {
        if (\is_string($controllers)) {
            $controllers = [$controllcers];
        }

        foreach ($controllers as $controllerName => $controller) {
            if (\is_string($controller)) { // 'stimulus_controller' => ['controllerName1', 'controllerName2']
                $this->stimulusAttributes->addController($controller);
            } elseif (\is_array($controller)) { // 'stimulus_controller' => ['controllerName' => ['values' => ['key' => 'value'], 'classes' => ['key' => 'value'], 'targets' => ['otherControllerName' => '.targetName']]]
                $this->stimulusAttributes->addController((string) $controllerName, $controller['values'] ?? [], $controller['classes'] ?? [], $controller['outlets'] ?? []);
            }
        }
    }

    private function handleTarget(array $targets): void
    {
        foreach ($targets as $controllerName => $target) {
            $this->stimulusAttributes->addTarget($controllerName, \is_array($target) ? implode(' ', $target) : $target);
        }
    }

    private function handleAction(string|array $actions): void
    {
        // 'stimulus_action' => 'controllerName#actionName'
        // 'stimulus_action' => 'eventName->controllerName#actionName'
        if (\is_string($actions) && str_contains($actions, '#')) {
            $eventName = null;

            if (str_contains($actions, '->')) {
                [$eventName, $rest] = explode('->', $actions, 2);
            } else {
                $rest = $actions;
            }

            [$controllerName, $actionName] = explode('#', $rest, 2);

            $this->stimulusAttributes->addAction($controllerName, $actionName, $eventName);

            return;
        }

        foreach ($actions as $controllerName => $action) {
            if (\is_string($action)) { // 'stimulus_action' => ['controllerName' => 'actionName']
                $this->stimulusAttributes->addAction($controllerName, $action);
            } elseif (\is_array($action)) {
                foreach ($action as $eventName => $actionName) {
                    if (\is_string($actionName)) { // 'stimulus_action' => ['controllerName' => ['eventName' => 'actionName']]
                        $this->stimulusAttributes->addAction($controllerName, $actionName, $eventName);
                    } elseif (\is_array($actionName)) { // 'stimulus_action' => ['controllerName' => ['eventName' => ['actionName' => ['key' => 'value']]]]
                        foreach ($actionName as $index => $params) {
                            $this->stimulusAttributes->addAction($controllerName, $index, $eventName, $params);
                        }
                    }
                }
            }
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'stimulus_action' => null,
            'stimulus_controller' => null,
            'stimulus_target' => null,
        ]);

        $resolver->setAllowedTypes('stimulus_action', ['string', 'array', 'null']);
        $resolver->setAllowedTypes('stimulus_controller', ['string', 'array', 'null']);
        $resolver->setAllowedTypes('stimulus_target', ['string', 'array', 'null']);
    }
}
