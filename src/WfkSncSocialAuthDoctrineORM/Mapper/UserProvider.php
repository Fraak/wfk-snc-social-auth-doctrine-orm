<?php

namespace WfkSncSocialAuthDoctrineORM\Mapper;

use Doctrine\ORM\EntityManager;
use ScnSocialAuth\Mapper\UserProviderInterface;
use WfkSncSocialAuthDoctrineORM\Options\ModuleOptions;
use ScnSocialAuth\Entity\UserProvider as UserProviderEntity;
use ZfcUser\Entity\UserInterface;
use Hybrid_User_Profile;

class UserProvider implements UserProviderInterface
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \ZfcUserDoctrineORM\Options\ModuleOptions
     */
    protected $options;

    /**
     * @var object
     */
    protected $entityPrototype;

    /**
     * @param EntityManager $em
     * @param ModuleOptions $options
     */
    public function __construct(EntityManager $em, ModuleOptions $options)
    {
        $this->em      = $em;
        $this->options = $options;
    }

    /**
     * @return object
     */
    public function getEntityPrototype()
    {
        if ($this->entityPrototype === null)
        {
            $entityType = $this->options->getUserProviderEntityClass();
            $this->entityPrototype = new $entityType;
        }
        return $this->entityPrototype;
    }

    /**
     * @param $providerId
     * @param $provider
     * @return object|null
     */
    public function findUserByProviderId($providerId, $provider)
    {
        $select = $this->em->createQueryBuilder()
            ->from($this->options->getUserProviderEntityClass(), 'e')
            ->select('e')
            ->andWhere('e.providerId = ?1')
            ->andWhere('e.provider = ?2')
            ->setParameter(1, $providerId)
            ->setParameter(2, $provider)
            ->setMaxResults(1);

        try
        {
            return $select->getQuery()->getSingleResult();
        }
        catch(\Doctrine\ORM\NoResultException $exception)
        {
            return null;
        }
    }

    /**
     * @param object $entity
     */
    public function insert($entity)
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    /**
     * @param UserInterface       $user
     * @param Hybrid_User_Profile $hybridUserProfile
     * @param string              $provider
     * @param array               $accessToken
     * @throws \ScnSocialAuth\Mapper\Exception\RuntimeException
     * @return void
     */
    public function linkUserToProvider(UserInterface $user, Hybrid_User_Profile $hybridUserProfile, $provider, array $accessToken = null)
    {
        $userProvider = $this->findUserByProviderId($hybridUserProfile->identifier, $provider);

        if (false != $userProvider) {
            if ($user->getId() == $userProvider->getUserId()) {
                // already linked
                return;
            }
            throw new \ScnSocialAuth\Mapper\Exception\RuntimeException('This ' . ucfirst($provider) . ' profile is already linked to another user.');
        }

        /** @var $userProvider UserProviderEntity */
        $userProvider = clone($this->getEntityPrototype());
        $userProvider->setUserId($user->getId())
            ->setProviderId($hybridUserProfile->identifier)
            ->setProvider($provider);

        $this->insert($userProvider);
    }
    
    /**
     * @param  UserInterface               $user
     * @param  string                      $provider
     * @return UserProviderInterface|false
     */
    public function findProviderByUser(UserInterface $user, $provider)
    {
        $select = $this->em->createQueryBuilder()
            ->from($this->options->getUserProviderEntityClass(), 'e')
            ->select('e')
            ->andWhere('e.userId = ?1')
            ->setParameter(1, $user->getId())
            ->setMaxResults(1);

        try
        {
            return $select->getQuery()->getSingleResult();
        }
        catch(\Doctrine\ORM\NoResultException $exception)
        {
            return null;
        }
    }

    /**
     * @param  UserInterface $user
     * @return array
     */
    public function findProvidersByUser(UserInterface $user)
    {
        $select = $this->em->createQueryBuilder()
            ->from($this->options->getUserProviderEntityClass(), 'e')
            ->select('e.provider')
            ->andWhere('e.userId = ?1')
            ->setParameter(1, $user->getId());

        return $select->getQuery()->getResult();
    }
}
