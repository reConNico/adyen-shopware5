<?php

use AdyenPayment\Components\IncomingNotificationManager;
use AdyenPayment\Exceptions\AuthorizationException;
use AdyenPayment\Exceptions\InvalidRequestPayloadException;
use AdyenPayment\Http\Response\NotificationResponseFactory;
use AdyenPayment\Http\Validator\Notification\NotificationValidatorInterface;
use AdyenPayment\Models\Event;
use Psr\Log\LoggerInterface;
use Shopware\Components\ContainerAwareEventManager;
use Shopware\Components\CSRFWhitelistAware;
use Symfony\Component\HttpFoundation\JsonResponse;

//phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ValidClassName.NotCamelCaps, Generic.Files.LineLength.TooLong
class Shopware_Controllers_Frontend_Notification extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /** @var ContainerAwareEventManager */
    private $events;

    /** @var IncomingNotificationManager */
    private $incomingNotificationsManager;

    /** @var LoggerInterface */
    private $logger;

    /** @var NotificationValidatorInterface */
    private $authorizationValidator;

    /**
     * @throws Exception
     *
     * @return void
     */
    public function preDispatch()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $this->events = $this->get('events');
        $this->incomingNotificationsManager = $this->get('AdyenPayment\Components\IncomingNotificationManager');
        $this->logger = $this->get('adyen_payment.logger.notifications');
        $this->authorizationValidator = $this->get('AdyenPayment\Http\Validator\Notification\AuthorizationValidator');
    }

    /**
     * @return void
     */
    public function postDispatch()
    {
        $data = $this->View()->getAssign();
        $response = $data['responseData'] ?? null;
        if (!$response instanceof JsonResponse) {
            $response = NotificationResponseFactory::fromShopwareResponse($this->Request(), $data);
        }
        $this->Response()->setHeader('Content-type', $response->headers->get('Content-Type'), true);
        $this->Response()->setHttpResponseCode($response->getStatusCode());
        $this->Response()->setBody($response->getContent());
    }

    /**
     * POST: /notification/adyen
     *
     * @return void
     */
    public function adyenAction()
    {
        try {
            $notifications = $this->getNotificationItems();
            $this->authorizationValidator->validate($notifications);

            if (!$this->saveTextNotification($notifications)) {
                $this->View()->assign('[notification save error]');
                return;
            }
        } catch (AuthorizationException $exception) {
            $this->View()->assign('responseData', NotificationResponseFactory::unauthorized($exception->getMessage()));

            return;
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
                'previous' => $exception->getPrevious(),
            ]);

            $this->View()->assign('responseData', NotificationResponseFactory::badRequest($exception->getMessage()));
            return;
        }

        // on valid credentials, always return ACCEPTED
        $this->View()->assign('responseData', NotificationResponseFactory::accepted());
    }

    /**
     * Whitelist notifyAction
     *
     * @return string[]
     *
     * @psalm-return array{0: 'adyen'}
     */
    public function getWhitelistedCSRFActions()
    {
        return ['adyen'];
    }

    /**
     * @return array|mixed
     * @throws Enlight_Event_Exception
     */
    private function getNotificationItems()
    {
        $rawBody = $this->Request()->getRawBody();
        if (empty($rawBody)) {
            throw InvalidRequestPayloadException::missingBody();
        }

        $jsonbody = json_decode($rawBody, true);
        if (!is_array($jsonbody)) {
            throw InvalidRequestPayloadException::invalidBody();
        }

        $notificationItems = $jsonbody['notificationItems'] ?? [];
        if (!$notificationItems) {
            return [];
        }

        $this->events->notify(
            Event::NOTIFICATION_RECEIVE,
            [
                'items' => $notificationItems,
            ]
        );

        return $notificationItems;
    }

    /**
     * @param array $notifications
     *
     * @throws Enlight_Event_Exception
     */
    private function saveTextNotification(array $notifications): bool
    {
        $notifications = $this->events->filter(
            Event::NOTIFICATION_SAVE_FILTER_NOTIFICATIONS,
            $notifications
        );

        return iterator_count($this->incomingNotificationsManager->saveTextNotification($notifications)) === 0;
    }
}
