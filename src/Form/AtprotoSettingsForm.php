<?php

declare(strict_types=1);

namespace Drupal\atproto\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure atproto_client settings for this site.
 */
final class AtprotoSettingsForm extends ConfigFormBase
{
    /**
     * The key factory.
     *
     * @var KeyRepositoryInterface
     */
    protected $keyRepository;

    final public function __construct(KeyRepositoryInterface $key_repository)
    {
        $this->keyRepository = $key_repository;
    }

    final public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('key.repository'),
        );
    }

    /*
    * {@inheritdoc}
    */
    public function getFormId(): string
    {
        return 'atproto_settings';
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames(): array
    {
        return ['atproto.settings'];
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state): array
    {
        $config = $this->config('atproto.settings');

		$form['pds'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AT Proto PDS'),
        '#description' => $this->t('The PDS your Atproto account is hosted on.'),
        '#default_value' => $config->get('pds'),
        ];


        $form['handle'] = [
        '#type' => 'textfield',
        '#title' => $this->t('AT Proto Handle'),
        '#description' => $this->t('AT Proto handle, without the @.'),
        '#default_value' => $config->get('handle'),
        ];

        $form['app_key'] = [
        '#type' => 'key_select',
        '#title' => $this->t('AT Proto App Password'),
        '#description' => $this->t('The App Password. Create this on your Bluesky profile, and then paste it here.'),
        '#default_value' => $config->get('app_key'),
        ];
  
        return parent::buildForm($form, $form_state);
    }


    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state): void
    {
        // Validate the api key against model listing.
        $key = $form_state->getValue('app_key');
        $app_key = $this->keyRepository->getKey($key)->getKeyValue();
        if (!$app_key) {
            $form_state->setErrorByName('app_key', $this->t('The App Key is invalid.'));
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state): void
    {
        $this->config('atproto.settings')
        	->set('pds', $form_state->getValue('pds'))
            ->set('handle', $form_state->getValue('handle'))
            ->set('app_key', $form_state->getValue('app_key'))
            ->save();
        parent::submitForm($form, $form_state);
    }

}
