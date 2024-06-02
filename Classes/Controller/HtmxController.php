<?php

declare(strict_types=1);

namespace Wwwision\HtmxTest\Controller;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindDescendantNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\Pagination\Pagination;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

/**
 * Neos backend module controller for this package
 */
final class HtmxController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    /**
     * how many numbers to load at once in the list view {@see indexAction}
     */
    private const NODES_PER_PAGE = 20;

    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {
    }

    protected function initializeView(ViewInterface $view): void
    {
        parent::initializeView($view);
        // If we're in a HTMX-request...
        if ($this->request->getHttpRequest()->hasHeader('HX-Request')) {
            // We append an "/htmx" segment to the fusion path, changing it from "<PackageKey>/<ControllerName>/<ActionName>" to "<PackageKey>/<ControllerName>/<ActionName>/htmx"
            $htmxFusionPath = str_replace(['\\Controller\\', '\\'], ['\\', '/'], $this->request->getControllerObjectName());
            $htmxFusionPath .= '/' . $this->request->getControllerActionName() . '/htmx';
            $view->setOption('fusionPath', $htmxFusionPath);
        }
    }

    public function indexAction(int $page = 1): void
    {
        $subgraph = $this->getDefaultContentSubgraph();
        $rootNode = $subgraph->findRootNodeByType(NodeTypeName::fromString(NodeTypeNameFactory::NAME_SITES));
        assert($rootNode !== null);
        $this->view->assign('page', $page);
        $this->view->assign('nodes', $subgraph->findDescendantNodes(
            $rootNode->aggregateId,
            FindDescendantNodesFilter::create(
                pagination: Pagination::fromLimitAndOffset(self::NODES_PER_PAGE, self::NODES_PER_PAGE * ($page - 1)),
            )
        ));
    }

    public function showAction(string $id): void
    {
        $subgraph = $this->getDefaultContentSubgraph();
        $this->view->assign('node', $subgraph->findNodeById(NodeAggregateId::fromString($id)));
    }

    private function getDefaultContentSubgraph(): ContentSubgraphInterface
    {
        // This is hard-coded to use the "default" content repository to keep things simple
        $contentRepository = $this->contentRepositoryRegistry->get(ContentRepositoryId::fromString('default'));
        // And we are only interested in nodes from the "live" workspace
        return $contentRepository->getContentGraph(WorkspaceName::forLive())->getSubgraph(
            // ...and this is hard-coded to the default dimension of the Neos.Demo package!
            DimensionSpacePoint::fromArray(['language' => 'en_US']),
            // ...and we load disabled nodes, too, since we're in a backend module
            VisibilityConstraints::withoutRestrictions()
        );
    }
}
