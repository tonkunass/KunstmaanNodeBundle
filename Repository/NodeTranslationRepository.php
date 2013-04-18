<?php

namespace Kunstmaan\NodeBundle\Repository;

use Kunstmaan\NodeBundle\Entity\HasNodeInterface;
use Kunstmaan\NodeBundle\Entity\Node;
use Kunstmaan\NodeBundle\Entity\NodeTranslation;
use Kunstmaan\NodeBundle\Entity\NodeVersion;
use Kunstmaan\UtilitiesBundle\Helper\Slugifier;
use Kunstmaan\AdminBundle\Entity\User as Baseuser;
use Kunstmaan\UtilitiesBundle\Helper\ClassLookup;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

/**
 * NodeRepository
 *
 */
class NodeTranslationRepository extends EntityRepository
{
    /**
     * Get all childs of a given node
     *
     * @param Node $node
     *
     * @return array
     */
    public function getChildren(Node $node)
    {
        return $this->findBy(array("parent" => $node->getId()));
    }

    /**
     * This returns the node translations that are visible for guest users
     *
     * @return array
     */
    public function getOnlineNodes()
    {
        return $this->createQueryBuilder('b')
                   ->select('b')
                   ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
                   ->where('n.deleted != 1 AND b.online = 1');
    }

    /**
     * Get the nodetranslation for a node
     *
     * @param HasNodeInterface $hasNode
     *
     * @return NodeTranslation
     */
    public function getNodeTranslationFor(HasNodeInterface $hasNode)
    {
        /* @var NodeVersion $nodeVersion */
        $nodeVersion = $this->getEntityManager()
            ->getRepository('KunstmaanNodeBundle:NodeVersion')
            ->getNodeVersionFor($hasNode);

        if (!is_null($nodeVersion)) {
            return $nodeVersion->getNodeTranslation();
        }

        return null;
    }

    /**
     * Get the node translation for a given slug string
     *
     * @param string               $slug       The slug
     * @param NodeTranslation|null $parentNode The parentnode
     *
     * @return NodeTranslation|null
     */
    public function getNodeTranslationForSlug($slug, NodeTranslation $parentNode = null)
    {
        if (empty($slug)) {
            return $this->getNodeTranslationForSlugPart(null, $slug);
        }

        $slugParts = explode('/', $slug);
        $result    = $parentNode;
        foreach ($slugParts as $slugPart) {
            $result = $this->getNodeTranslationForSlugPart($result, $slugPart);
        }

        return $result;
    }

    /**
     * Get the node translation for a given url
     *
     * @param string          $urlSlug        The full url
     * @param string          $locale         The locale
     * @param boolean         $includeDeleted Include deleted
     * @param NodeTranslation $toExclude      To exclude
     *
     * @return NodeTranslation|null
     */
    public function getNodeTranslationForUrl($urlSlug, $locale = '', $includeDeleted = false, NodeTranslation $toExclude = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b')
            ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
            ->addOrderBy('n.sequenceNumber', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(1);

        if (!$includeDeleted) {
            $qb->andWhere('n.deleted = 0');
        }

        if (!empty($locale)) {
            $qb->andWhere('b.lang = :lang')
               ->setParameter('lang', $locale);
        }

        if (empty($urlSlug)) {
            $qb->andWhere('b.url IS NULL');
        } else {
            $qb->andWhere('b.url = :url');
            $qb->setParameter('url', $urlSlug);
        }

        if (!is_null($toExclude)) {
            $qb->andWhere('NOT b.id = :exclude_id')
               ->setParameter('exclude_id', $toExclude->getId());
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Get all top node translations
     *
     * @return NodeTranslation[]
     */
    public function getTopNodeTranslations()
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b')
            ->innerJoin('b.node', 'n', 'WITH', 'b.node = n.id')
            ->where('n.parent IS NULL')
            ->andWhere('n.deleted != 1');

        return $qb->getQuery()->getResult();
    }

    /**
     * Returns the node translation for a given slug
     *
     * @param NodeTranslation|null $parentNode The parentNode
     * @param string               $slugPart   The slug part
     *
     * @return NodeTranslation|null
     */
    private function getNodeTranslationForSlugPart(NodeTranslation $parentNode = null, $slugPart = '')
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t')
            ->innerJoin('t.node', 'n', 'WITH', 't.node = n.id')
            ->where('n.deleted != 1')
            ->addOrderBy('n.sequenceNumber', 'DESC')
            ->setFirstResult(0)
            ->setMaxResults(1);

        if ($parentNode != null) {
            $qb->andWhere('t.slug = :slug')
               ->andWhere('n.parent = :parent')
               ->setParameter('slug', $slugPart)
               ->setParameter('parent', $parentNode->getNode()->getId());
        } else {
            /* if parent is null we should look for slugs that have no parent */
            $qb->andWhere('n.parent IS NULL');
            if (empty($slugPart)) {
                $qb->andWhere('t.slug is NULL');
            } else {
                $qb->andWhere('t.slug = :slug');
                $qb->setParameter('slug', $slugPart);
            }
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Create a node translation for a given node
     *
     * @param HasNodeInterface $hasNode The hasNode
     * @param string           $lang    The locale
     * @param Node             $node    The node
     * @param Baseuser         $owner   The user
     *
     * @throws \InvalidArgumentException
     *
     * @return NodeTranslation
     */
    public function createNodeTranslationFor(HasNodeInterface $hasNode, $lang, Node $node, Baseuser $owner)
    {
        $em        = $this->getEntityManager();
        $className = ClassLookup::getClass($hasNode);
        if (!$hasNode->getId() > 0) {
            throw new \InvalidArgumentException("The entity of class " . $className . " has no id, maybe you forgot to flush first");
        }

        $nodeTranslation = new NodeTranslation();
        $nodeTranslation
            ->setNode($node)
            ->setLang($lang)
            ->setTitle($hasNode->getTitle())
            ->setSlug(Slugifier::slugify($hasNode->getTitle(), ''))
            ->setOnline(false)
            ->setWeight(0);

        $em->persist($nodeTranslation);

        $nodeVersion = $em->getRepository('KunstmaanNodeBundle:NodeVersion')->createNodeVersionFor($hasNode, $nodeTranslation, $owner, null);

        $nodeTranslation->setPublicNodeVersion($nodeVersion);
        $em->persist($nodeTranslation);
        $em->flush();
        $em->refresh($nodeTranslation);
        $em->refresh($node);

        return $nodeTranslation;
    }

    /**
     * Find best match for given URL and locale
     *
     * @param string $urlSlug The slug
     * @param string $locale  The locale
     *
     * @return NodeTranslation
     */
    public function getBestMatchForUrl($urlSlug, $locale)
    {
        $em = $this->getEntityManager();

        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata('Kunstmaan\NodeBundle\Entity\NodeTranslation', 'nt');

        $query = $em
            ->createNativeQuery(
                'select nt.*
                from kuma_node_translations nt
                join kuma_nodes n on n.id = nt.node_id
                where n.deleted = 0 and nt.lang = :lang and locate(nt.url, :url) = 1
                order by length(nt.url) desc limit 1',
                $rsm
            );
        $query->setParameter('lang', $locale);
        $query->setParameter('url', $urlSlug);
        $translation = $query->getOneOrNullResult();

        return $translation;
    }


    /**
     * Look if all parents of a NodeTranslation have NodeTranslations
     * @param NodeTranslation $nodeTranslation The node translation
     * @param string          $language        The locale
     *
     * @return bool
     */
    public function hasParentNodeTranslationsForLanguage(NodeTranslation $nodeTranslation, $language)
    {
        $parentNode = $nodeTranslation->getNode()->getParent();
        if ($parentNode != null) {
            $parentNodeTranslation = $parentNode->getNodeTranslation($language, true);
            if ($parentNodeTranslation != null) {
                return $this->hasParentNodeTranslationsForLanguage($parentNodeTranslation, $language);
            } else {
                return false;
            }
        }

        return true;
    }
}
