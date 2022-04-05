<?php

namespace Kunstmaan\NodeBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Kunstmaan\AdminBundle\Helper\DomainConfigurationInterface;
use Kunstmaan\AdminBundle\Helper\Security\Acl\Permission\PermissionMap;
use Kunstmaan\NodeBundle\Entity\Node;
use Kunstmaan\NodeBundle\Entity\StructureNode;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class WidgetsController extends Controller
{
    /**
     * @Route("/ckselecturl", name="KunstmaanNodeBundle_ckselecturl")
     * @Template("@KunstmaanNode/Widgets/selectLink.html.twig")
     *
     * @return array
     */
    public function ckSelectLinkAction(Request $request)
    {
        $params = $this->getTemplateParameters($request);
        $params['cke'] = true;
        $params['multilanguage'] = $this->getParameter('kunstmaan_admin.multi_language');

        return $params;
    }

    /**
     * Select a link
     *
     * @Route("/selecturl", name="KunstmaanNodeBundle_selecturl")
     * @Template("@KunstmaanNode/Widgets/selectLink.html.twig")
     *
     * @return array
     */
    public function selectLinkAction(Request $request)
    {
        $params = $this->getTemplateParameters($request);
        $params['cke'] = false;
        $params['multilanguage'] = $this->getParameter('kunstmaan_admin.multi_language');

        return $params;
    }

    /**
     * Select a link
     *
     * @Route("/select-nodes-lazy", name="KunstmaanNodeBundle_nodes_lazy")
     *
     * @return JsonResponse
     */
    public function selectNodesLazy(Request $request)
    {
        /* @var EntityManagerInterface $em */
        $em = $this->getDoctrine()->getManager();
        $locale = $request->getLocale();
        $id = $request->query->get('id');
        $depth = $this->getParameter('kunstmaan_node.url_chooser.lazy_increment');

        if (!$id || $id == '#') {
            $domainConfig = $this->get('kunstmaan_admin.domain_configuration');

            if ($domainConfig->isMultiDomainHost()) {
                $switchedHost = $domainConfig->getHostSwitched();
                $rootItems = [$domainConfig->getRootNode($switchedHost['host'])];
            } else {
                $rootItems = $em->getRepository(Node::class)->getAllTopNodes();
            }
        } else {
            $rootNode = $em->getRepository(Node::class)->find($id);
            $rootItems = $this->getChildren($locale, $rootNode);
        }

        $results = $this->nodesToArray($locale, $rootItems, $depth);

        return new JsonResponse($results);
    }

    /**
     * Get the parameters needed in the template. This is common for the
     * default link chooser and the cke link chooser.
     *
     * @return array
     */
    private function getTemplateParameters(Request $request)
    {
        // When the media bundle is available, we show a link in the header to the media chooser
        $allBundles = $this->getParameter('kernel.bundles');
        $mediaChooserLink = null;

        if (\array_key_exists('KunstmaanMediaBundle', $allBundles)) {
            $params = ['linkChooser' => 1];
            $cKEditorFuncNum = $request->get('CKEditorFuncNum');
            if (!empty($cKEditorFuncNum)) {
                $params['CKEditorFuncNum'] = $cKEditorFuncNum;
            }
            $mediaChooserLink = $this->generateUrl(
                'KunstmaanMediaBundle_chooser',
                $params
            );
        }

        return [
            'mediaChooserLink' => $mediaChooserLink,
        ];
    }

    /**
     * Determine if current node is a structure node.
     *
     * @param string $refEntityName
     *
     * @return bool
     */
    protected function isStructureNode($refEntityName)
    {
        $structureNode = false;
        if (class_exists($refEntityName)) {
            $page = new $refEntityName();
            $structureNode = ($page instanceof StructureNode);
            unset($page);
        }

        return $structureNode;
    }

    /**
     * Determine if current node is a structure node.
     *
     * @param string                 $locale
     * @param Node[]|ArrayCollection $rootNodes
     * @param int                    $depth
     *
     * @return array
     */
    protected function nodesToArray($locale, $rootNodes, $depth = 2)
    {
        /** @var DomainConfigurationInterface $domainconfig */
        $domainconfig = $this->get('kunstmaan_admin.domain_configuration');
        $isMultiDomain = $domainconfig->isMultiDomainHost();
        $switchedHost = $domainconfig->getHostSwitched();
        $switched = null !== $switchedHost && array_key_exists('host', $switchedHost) && $domainconfig->getHost() === $switchedHost['host'];

        $results = [];

        /** @var Node $rootNode */
        foreach ($rootNodes as $rootNode) {
            if ($nodeTranslation = $rootNode->getNodeTranslation($locale, true)) {
                if ($isMultiDomain && !$switched) {
                    $slug = sprintf('[%s:%s]', $switchedHost['id'], 'NT' . $nodeTranslation->getId());
                } else {
                    $slug = sprintf('[%s]', 'NT' . $nodeTranslation->getId());
                }

                switch (true) {
                    case !$nodeTranslation->isOnline():
                        $type = 'offline';

                        break;
                    case $rootNode->isHiddenFromNav():
                        $type = 'hidden-from-nav';

                        break;
                    default:
                        $type = 'default';
                }

                $root = [
                    'id' => $rootNode->getId(),
                    'type' => $type,
                    'text' => $nodeTranslation->getTitle(),
                    'li_attr' => ['class' => 'js-url-chooser-link-select', 'data-slug' => $slug, 'data-id' => $rootNode->getId()],
                ];

                $children = $this->getChildren($locale, $rootNode);
                if ($children) {
                    if ($depth - 1) {
                        $root['children'] = $this->nodesToArray($locale, $children, --$depth);
                    } else {
                        $root['children'] = true;
                    }
                }
                $results[] = $root;
            }
        }

        return $results;
    }

    private function getChildren(string $locale, Node $rootNode)
    {
        $em = $this->getDoctrine()->getManager();
        $nodeRepository = $em->getRepository(Node::class);

        $aclHelper = $this->container->get('kunstmaan_admin.acl.helper');

        return $nodeRepository->getChildNodes(
            $rootNode->getId(),
            $locale,
            PermissionMap::PERMISSION_VIEW,
            $aclHelper,
            true,
            true,
            $rootNode
        );
    }
}
