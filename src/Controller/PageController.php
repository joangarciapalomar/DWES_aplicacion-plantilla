<?php

namespace App\Controller;

use App\Entity\Imagen;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Form\ImagenType;
use Symfony\Component\Filesystem\Filesystem;

class PageController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(SessionInterface $session, ManagerRegistry $doctrine): Response
    {
        $repositorio = $doctrine->getRepository(Imagen::class);
        if ($this->getUser()) {
            $imagenes = $repositorio->findAll();
            return $this->render('page/index.html.twig', [
                'imagenes' => $imagenes,
                'controller_name' => 'PageController',
            ]);
        } else {
            $session->set('redirect_to', 'app_index');
            return $this->redirectToRoute("app_login");
        }
    }

    #[Route('/image/edit/{codigo}', name: 'app_edit')]
    public function edit(ManagerRegistry $doctrine, SluggerInterface $slugger, Request $request, $codigo, SessionInterface $session): Response
    {
        if ($this->getUser()) {
            $entityManager = $doctrine->getManager();
            $repositorio = $doctrine->getRepository(Imagen::class);
            $imagen = $repositorio->find($codigo);

            if (!$imagen) {
                // Si la imagen no se encuentra, se crea una nueva instancia de Imagen
                $imagen = new Imagen();
            }

            $formulario = $this->createForm(ImagenType::class, $imagen);
            $formulario->handleRequest($request);

            if ($formulario->isSubmitted() && $formulario->isValid()) {
                $imagen = $formulario->getData();

                $file = $formulario->get('file')->getData();
                if ($file) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                    // Mover el archivo al directorio donde se almacenan las imágenes
                    try {
                        $file->move(
                            $this->getParameter('portfolio_directory'),
                            $newFilename
                        );

                        $fileSystem = new Filesystem();
                        $sourcePath = $this->getParameter('portfolio_directory') . '/' . $newFilename;
                        $destinationPath = 'public/assets/img/portfolio' . $newFilename; // Define the destination directory
                        $fileSystem->copy($sourcePath, $destinationPath);

                    } catch (FileException $e) {
                        // Manejar la excepción si ocurre algo durante la carga del archivo
                    }
                }

                // Guardar los cambios en la entidad principal
                $entityManager->persist($imagen);
                $entityManager->flush();

                // Redirigir a la página index con el código de la imagen editada
                return $this->redirectToRoute('app_index', ['codigo' => $imagen->getId()]);
            }

            // Renderizar el formulario para editar/crear la imagen
            return $this->render('editar.html.twig', [
                'formulario' => $formulario->createView(),
                'imagen' => $imagen,
            ]);
        } else {
            // Si el usuario no está autenticado, redirigir al inicio de sesión
            $session->set('redirect_to', 'app_edit');
            $session->set('codigo', $codigo);
            return $this->redirectToRoute('app_login');
        }
    }
}
