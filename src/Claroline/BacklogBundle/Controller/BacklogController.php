<?php

namespace Claroline\BacklogBundle\Controller;

use Claroline\BacklogBundle\Entity\Ticket;
use Claroline\BacklogBundle\Entity\Status;
use Claroline\BacklogBundle\Entity\Version;
use Claroline\BacklogBundle\Entity\Category;
use Claroline\BacklogBundle\Entity\Package;
use Claroline\BacklogBundle\Entity\Role;
use Claroline\BacklogBundle\Entity\Team;
use Claroline\BacklogBundle\Entity\Comment;
use Claroline\BacklogBundle\Form\TicketType;
use Claroline\BacklogBundle\Form\StatusType;
use Claroline\BacklogBundle\Form\VersionType;
use Claroline\BacklogBundle\Form\CategoryType;
use Claroline\BacklogBundle\Form\PackageType;
use Claroline\BacklogBundle\Form\RoleType;
use Claroline\BacklogBundle\Form\TeamType;
use Claroline\BacklogBundle\Form\CommentType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as EXT;

class BacklogController extends Controller
{
    /**
     * @EXT\Route("/", name="tickets")
     * @EXT\Template
     */
    public function ticketsAction(Request $request)
    {
        $qb = $this->get('doctrine.orm.entity_manager')
            ->createQueryBuilder()
            ->select('t')
            ->from('Claroline\BacklogBundle\Entity\Ticket', 't');

        if ($orderBy = $request->query->get('order')) {
            $allowedFields = array(
                'title', 'priority', 'status', 'creator', 'version', 'isValidated'
            );

            if (!in_array($orderBy, $allowedFields)) {
                return new Response('Bad request', 400);
            }

            $direction = $request->query->get('direction') === 'desc' ? 'desc' : 'asc';
            $qb->orderBy('t.' . $orderBy, strtoupper($direction));
        }

        return array(
            'tickets' => $qb->getQuery()->getResult(),
            'direction' => isset($direction) ? ($direction === 'asc' ? 'desc' : 'asc') : 'asc'
        );
    }

    /**
     * @EXT\Route("/tickets/new", name="create_ticket")
     * @EXT\Template("ClarolineBacklogBundle:Backlog:ticketForm.html.twig")
     */
    public function createTicketAction(Request $request)
    {
        $form = $this->createForm(new TicketType(), new Ticket());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $ticket = $form->getData();
                $ticket->setCreator($this->getUser());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($ticket);
                $em->flush();

                return $this->redirect($this->generateUrl('tickets'));
            }
        }

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route("/tickets/update/{ticket}", name="update_ticket")
     * @EXT\Template("ClarolineBacklogBundle:Backlog:ticketForm.html.twig")
     * @EXT\ParamConverter("ticket", class="ClarolineBacklogBundle:Ticket")
     * @EXT\Template("ClarolineBacklogBundle:Backlog:ticketForm.html.twig")
     */
    public function updateTicketAction(Request $request, Ticket $ticket)
    {
        if ($this->getUser() != $ticket->getCreator() and false === $this->get('security.context')->isGranted('ROLE_ADMIN') ){
            throw new AccessDeniedException();
        }
        $form = $this->createForm(new TicketType(), $ticket);

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($ticket);
                $em->flush();

                return $this->redirect($this->generateUrl('tickets'));
            }
        }

        return array('form' => $form->createView());

    }

    /**
     * @EXT\Route("/tickets/view/{ticket}", name="view_ticket")
     * @EXT\Template("ClarolineBacklogBundle:Backlog:ticketView.html.twig")
     * @EXT\ParamConverter("ticket", class="ClarolineBacklogBundle:Ticket")
     */

    public function viewTicketAction(Request $request, Ticket $ticket)
    {
        $ticket = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Ticket')
            ->find($ticket);

        $form = $this->createForm(new CommentType(), new Comment());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $comment= new Comment();
                $comment->setContent($form->get('content')->getData());
                $comment->setCreator($this->getUser());
                $comment->setCreated(new \DateTime());
                $em = $this->get('doctrine.orm.entity_manager');
                $ticket->addComment($comment);
                $comment->setTicket($ticket);
                $em->persist($comment);
                $em->persist($ticket);
                $em->flush();

                //return $this->redirect($this->generateUrl(''));
                return $this->redirect($this->generateUrl('view_ticket', array('ticket'=>$ticket->getId())));
            }
        }

        return array('ticket' => $ticket, 'form' => $form->createView());

    }

    /**
     * @EXT\Route("/status", name="status")
     * @EXT\Template
     */
    public function statusAction()
    {
        $status = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Status')
            ->findAll();

        return array('status' => $status);
    }

    /**
     * @EXT\Route("/status/new", name="create_status")
     * @EXT\Template'("ClarolineBacklogBundle:Backlog:statusForm.html.twig")
     */
    public function statusFormAction(Request $request)
    {
        $form = $this->createForm(new StatusType(), new Status());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $status = new Status();
                $status->setStatusName($form->get('statusName')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($status);
                $em->flush();

                return $this->redirect($this->generateUrl('status'));
            }
        }

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route("/version", name="version")
     * @EXT\Template
     */
    public function versionAction()
    {
        $version = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Version')
            ->findAll();

        return array('versions' => $version);
    }

    /**
     * @EXT\Route("/version/new", name="create_version")
     * @EXT\Template
     */
    public function versionFormAction(Request $request)
    {
        $form = $this->createForm(new VersionType(), new Version());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $version = new Version();
                $version->setVersionName($form->get('versionName')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($version);
                $em->flush();

                return $this->redirect($this->generateUrl('version'));
            }
        }

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route("/category", name="category")
     * @EXT\Template
     */
    public function categoryAction()
    {
        $categories = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Category')
            ->findAll();

        return array('categories' => $categories);
    }

    /**
     * @EXT\Route("/category/new", name="create_category")
     * @EXT\Template
     */
    public function categoryFormAction(Request $request)
    {
        $form = $this->createForm(new CategoryType(), new Category());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $category = new Category();
                $category->setName($form->get('name')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($category);
                $em->flush();

                return $this->redirect($this->generateUrl('category'));
            }
        }

        return array('form' => $form->createView());
    }


    /**
     * @EXT\Route("/package", name="package")
     * @EXT\Template
     */
    public function packageAction()
    {
        $packages = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Package')
            ->findAll();

        return array('packages' => $packages);
    }

    /**
     * @EXT\Route("/package/new", name="create_package")
     * @EXT\Template
     */
    public function packageFormAction(Request $request)
    {
        $form = $this->createForm(new PackageType(), new Package());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $package = new Package();
                $package->setName($form->get('name')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($package);
                $em->flush();

                return $this->redirect($this->generateUrl('package'));
            }
        }

        return array('form' => $form->createView());
    }


    /**
     * @EXT\Route("/role", name="role")
     * @EXT\Template
     */
    public function roleAction()
    {
        $roles = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Role')
            ->findAll();

        return array('roles' => $roles);
    }

    /**
     * @EXT\Route("/role/new", name="create_role")
     * @EXT\Template
     */
    public function roleFormAction(Request $request)
    {
        $form = $this->createForm(new RoleType(), new Role());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $role = new Role();
                $role->setName($form->get('name')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($role);
                $em->flush();

                return $this->redirect($this->generateUrl('role'));
            }
        }

        return array('form' => $form->createView());
    }

    /**
     * @EXT\Route("/team", name="team")
     * @EXT\Template
     */
    public function teamAction()
    {
        $teams = $this->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineBacklogBundle:Team')
            ->findAll();

        return array('teams' => $teams);
    }

    /**
     * @EXT\Route("/team/new", name="create_team")
     * @EXT\Template
     */
    public function teamFormAction(Request $request)
    {
        $form = $this->createForm(new TeamType(), new Team());

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isValid()) {
                $team = new Team();
                $team->setName($form->get('name')->getData());
                $em = $this->get('doctrine.orm.entity_manager');
                $em->persist($team);
                $em->flush();

                return $this->redirect($this->generateUrl('team'));
            }
        }

        return array('form' => $form->createView());
    }

}
