<?php

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
            isset($options['stimulus_controller'])
            || !isset($options['stimulus_target'])
            || !isset($options['stimulus_action'])
        ) {
            $this->stimulusAttributes = new StimulusAttributes(new Environment(new ArrayLoader()));

            if (isset($options['stimulus_controller'])) {
                $this->handleController($options['stimulus_controller']);
            }

            if (isset($options['stimulus_target'])) {
                $this->handleTarget($options['stimulus_target']);
            }

            if (isset($options['stimulus_action'])) {
                $this->handleAction($options['stimulus_action']);
            }

            $attributes = array_merge($view->vars['attr'], $this->stimulusAttributes->toArray());
            $view->vars['attr'] = $attributes;
        }

        foreach (['row_attr', 'choice_attr'] as $index) {
            if (
                isset($options[$index])
                && (
                    isset($options[$index]['stimulus_controller'])
                    || isset($options[$index]['stimulus_target'])
                    || isset($options[$index]['stimulus_action'])
                )
            ) {
                $this->stimulusAttributes = new StimulusAttributes(new Environment(new ArrayLoader()));

                if (isset($options[$index]['stimulus_controller'])) {
                    $this->handleController($options[$index]['stimulus_controller']);
                    unset($options[$index]['stimulus_controller']);
                }

                if (isset($options[$index]['stimulus_target'])) {
                    $this->handleTarget($options[$index]['stimulus_target']);
                    unset($options[$index]['stimulus_target']);
                }

                if (isset($options[$index]['stimulus_action'])) {
                    $this->handleAction($options[$index]['stimulus_action']);
                    unset($options[$index]['stimulus_action']);
                }

                $attributes = array_merge($options[$index], $this->stimulusAttributes->toArray());
                $view->vars[$index] = $attributes;
            }
        }
    }

    private function handleController(string|array $controllers): void
    {
        if (\is_string($controllers)) {
            $controllers = [$controllers];
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

        $resolver->setAllowedTypes('stimulus_action', ['string', 'array', 'callable', 'null']);
        $resolver->setAllowedTypes('stimulus_controller', ['string', 'array', 'callable', 'null']);
        $resolver->setAllowedTypes('stimulus_target', ['string', 'array', 'callable', 'null']);
    }
}
