<?php

namespace Oro\Bundle\EmailBundle\EmailSyncCredentials;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

use Oro\Bundle\ImapBundle\Entity\UserEmailOrigin;
use Oro\Bundle\UserBundle\Entity\User;

/**
 * The manager that process the notifications about wrong credential sync email boxes
 */
class SyncCredentialsIssueManager
{
    /** @var WrongCredentialsOriginsDriverInterface */
    private $credentialsDriver;

    /** @var AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var NotificationSenderInterface[] */
    private $notificationSenders = [];

    /** @var NotificationSenderInterface[] */
    private $userNotificationSenders = [];

    /**
     * @param WrongCredentialsOriginsDriverInterface $credentialsDriver
     * @param AuthorizationCheckerInterface $authorizationChecker
     */
    public function __construct(
        WrongCredentialsOriginsDriverInterface $credentialsDriver,
        AuthorizationCheckerInterface $authorizationChecker
    ) {
        $this->credentialsDriver = $credentialsDriver;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Adds the notification channel.
     *
     * @param NotificationSenderInterface $notificationSender
     */
    public function addNotificationSender(NotificationSenderInterface $notificationSender)
    {
        $this->notificationSenders[] = $notificationSender;
    }

    /**
     * Adds the notification channel.
     *
     * @param NotificationSenderInterface $notificationSender
     */
    public function addUserNotificationSender(NotificationSenderInterface $notificationSender)
    {
        $this->userNotificationSenders[] = $notificationSender;
    }

    /**
     * Stores information about wrong credential sync email box.
     *
     * @param UserEmailOrigin $emailOrigin
     */
    public function addInvalidOrigin(UserEmailOrigin $emailOrigin)
    {
        $userId = null;

        $originOwner = $emailOrigin->getOwner();
        if ($originOwner) {
            $userId = $originOwner->getId();
        }

        $this->credentialsDriver->addOrigin($emailOrigin->getId(), $userId);
    }

    /**
     * Sends the messages to the notification channels about wrong credential sync email boxes and deletes
     * the information about wrong boxes from the storage to avoid notification duplications.
     */
    public function processInvalidOrigins()
    {
        foreach ($this->credentialsDriver->getAllOrigins() as $invalidOrigin) {
            foreach ($this->notificationSenders as $notificationSender) {
                $notificationSender->sendNotification($invalidOrigin);
            }
        }

        $this->credentialsDriver->deleteAllOrigins();
    }

    public function processInvalidOriginsForUser(User $user)
    {
        $this->processUserOrigins($this->credentialsDriver->getAllOriginsByOwnerId($user->getId()));
        if ($this->authorizationChecker->isGranted('oro_email_credential_system_notifications')) {
            $this->processUserOrigins($this->credentialsDriver->getAllOriginsByOwnerId());
        }
    }

    private function processUserOrigins(array $invalidOrigins)
    {
        foreach ($invalidOrigins as $invalidOrigin) {
            foreach ($this->userNotificationSenders as $notificationSender) {
                $notificationSender->sendNotification($invalidOrigin);
            }
            $this->credentialsDriver->deleteOrigin($invalidOrigin->getId());
        }
    }

    /**
     * Removes the origin information. This method should be called after success sync of the email origin.
     *
     * @param UserEmailOrigin $emailOrigin
     */
    public function removeOriginFromTheFailed(UserEmailOrigin $emailOrigin)
    {
        $this->credentialsDriver->deleteOrigin($emailOrigin->getId());
    }
}
