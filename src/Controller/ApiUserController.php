<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;


class ApiUserController extends AbstractController
{

/*------------------------------------------------------------ CREATING ------------------------------------------------------------*/


                    /*------------ CREATE USER ------------*/

    /**
     * @Route("/api/user/add/info", name="api_user_add_info", methods={"POST"})
     */
    public function addUser(EntityManagerInterface $manager, SerializerInterface $serializer, Request $request, ValidatorInterface $validator, EncoderFactoryInterface $encoder, EncoderInterface $encoderJson)
    {
        $data = $request->getContent();
        $user = new User();


        $serializer->deserialize(
            $data, User::class, 
            'json', 
            [
                'groups' => 'registration',
                'object_to_populate' => $user,
                'disable_type_enforcement' => true
            ]
        );
        $errors = $validator->validate($user, null , 'registration');

        if (count($errors)) {
            $errorsJson = $serializer->serialize($errors, 'json');
            return new JsonResponse($errorsJson, Response::HTTP_BAD_REQUEST, [], true);
        } else {
            $user->setFirstname(htmlspecialchars($user->getFirstname()));
            $user->setLastname(htmlspecialchars($user->getLastname()));
            $user->setUsername(htmlspecialchars($user->getUsername()));
            $user->setEmail(htmlspecialchars($user->getEmail()));
            $user->setPassword(htmlspecialchars($user->getPassword()));
            $user->setActiveAccount(0);
            $user->setLanguage(true);
            $user->setActiveAccountKey(mt_rand(1000, 5000).uniqid().mt_rand(10000, 15000));

            $encoder = $encoder->getEncoder($user);
            $passwordEncoded = $encoder->encodePassword($user->getPassword(), null);
            $user->setPassword($passwordEncoded);
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse(
                "",
                Response::HTTP_CREATED,
                ['location' => 'http://localhost:8080/Hypertube/public/api/user/add/picture/'.$user->getId()],
                true
            );
        }
    }


                    /*------------ ADD IMAGE TO THE USER ------------*/

    /**
     * @Route("/api/user/add/picture/{id}", name="api_user_add_picture", methods={"POST"})
     */
    public function addUserPicture(User $user, EncoderInterface $encoder, EntityManagerInterface $manager)
    {
        if ($user->getActiveAccount() !== 0)
            return new JsonResponse("", Response::HTTP_UNAUTHORIZED, [], true);

        $error = null;      
        $valid_extensions = array('image/jpeg', 'image/png');
        $error_case = array(
            'You must select a profile picture.<br/>Vous devez choisir une image de profil.',
            'The picture is too large (max: 40mo).<br/>Le fichier est trop volumineux (max: 40mo).',
            'A problem occurred, and the picture could not be considered.<br/>Un problème est survenu, et la photo n\'a pas pu être prise en compte.',
            'Only JPG/JPEG and PNG images are allowed.<br/>Seules les images JPG/JPEG et PNG sont autorisées.'
        );
        
        if (empty($_FILES['profile_picture'])) {
            $manager->remove($user);
            $manager->flush();
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'profile_picture', 
                        'title' => $error_case[0]))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true
            );
        }

        else {
            $picture = $_FILES['profile_picture'];
            if ($picture['error'] == 1) $error = 1;                
            else if ($picture['error'] =! 0 && $picture['error'] =! 1) $error = 2;
            else if (!in_array(mime_content_type($picture['tmp_name']), $valid_extensions)) $error = 3;
        }
        if (!empty($error)) {
            $manager->remove($user);
            $manager->flush();
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'profile_picture', 
                        'title' => $error_case[$error]))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true
            );

        } else {
            $picture_extension = explode('.', $picture['name']);
            $picture_extension = strtolower(end($picture_extension));
            $picture_new_name = uniqid().".".$picture_extension;
            $picture_destination = $_SERVER['DOCUMENT_ROOT']."/Hypertube/public/Profile-pictures/".$picture_new_name;     
            move_uploaded_file($picture['tmp_name'], $picture_destination);
            $user->setProfilePicture('http://localhost:8080/Hypertube/public/Profile-pictures/'.$picture_new_name);
            $user->setActiveAccount(1);
            $manager->persist($user);
            $manager->flush();

            return new JsonResponse(
                $encoder->encode(array('id' => $user->getId()), 'json'),
                Response::HTTP_CREATED,
                ['location' => 'http://localhost:8080/Hypertube/public/api/user/send/email/active-account/'.$user->getId()],
                true
            );
        }       
    }


                    /*------------ SEND EMAIL TO CHECK USER EMAIL ADRESS AND ACTIVE HIS ACCOUNT ------------*/

    /**
     * @Route("/api/user/send/email/active-account/{id}", name="api_user_send_confirm_mail", methods={"POST"})
     */
    public function sendEmail(User $user, \Swift_Mailer $mailer, Request $request, DecoderInterface $decoder, EncoderInterface $encoder, EntityManagerInterface $manager)
    {
        if ($user->getActiveAccount() !== 1)
            return new JsonResponse("", Response::HTTP_UNAUTHORIZED, [], true);

        $data = $request->getContent();
        $data = $decoder->decode($data, 'json');

        if (empty($data['link']))
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'error', 'title' => 
                        'You must enter a link which will be sent in the mail.<br/>Vous devez entrer un lien qui sera envoyé dans l\'email.'))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true);
        
        $link = htmlspecialchars($data['link']);
        $message = new \Swift_Message('Registration - Inscription');
        $france_logo = $message->embed(\Swift_Image::fromPath('images-emails/france.png'));
        $usa_logo = $message->embed(\Swift_Image::fromPath('images-emails/united-states.png'));

        $message
            ->setFrom(['hypertube-ad@gmail.com' => 'Hypertube'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/registration.html.twig',
                    [
                        'france_logo' => $france_logo,
                        'usa_logo' => $usa_logo,
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'link' => $link.$user->getActiveAccountKey()
                    ]
                ),
                'text/html'
            )
        ;
        
        if ($mailer->send($message)) {
            $user->setActiveAccount(2);
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse("", Response::HTTP_OK, [], true);
        }
        else {
            $manager->remove($user);
            $manager->flush();
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'email', 
                        'title' => 'An error occurred and your registration could not be processed.<br/>Une erreur s\'est produit et votre inscription n\'a pas pu être prise en compte.'))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true
            );
        }
    }



                    /*------------ CHECK THE CONFIRM EMAIL KEY TO ACTIVE ACCOUNT ------------*/

    /**
     * @Route("/api/user/check/confirm-key/{id}", name="api_user_check_confirm_key", methods={"POST"})
     */
    public function checkConfirmKey(User $user, Request $request, DecoderInterface $decoder, EncoderInterface $encoder, EntityManagerInterface $manager)
    {
        if ($user->getActiveAccount() !== 2)
            return new JsonResponse("", Response::HTTP_UNAUTHORIZED, [], true);

        $data = $request->getContent();
        $data = $decoder->decode($data, 'json');

        if (empty($data['confirm_key']))
            return new JsonResponse(
                $encoder->encode(array('violations' => array(
                    array('propertyPath' => 'error',
                    'title' => 'You must enter the confirmation key which must be verified.<br/>Vous devez entrer la clé de confirmation qui doit être vérifiée.'))),
                    'json'
                ),
                Response::HTTP_BAD_REQUEST,
                [],
                true);
        
        else if ($data['confirm_key'] !== $user->getActiveAccountKey())
            return new JsonResponse(
                $encoder->encode(array('violations' => array(
                    array('propertyPath' => 'error',
                    'title' => 'The confirmation key does not match.<br/>La clé de confirmation ne correspond pas.'))),
                    'json'
                ),
                Response::HTTP_BAD_REQUEST,
                [],
                true);
        else {
            $user->setActiveAccount(3);
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse("", Response::HTTP_OK, [], true);
        }
    }


                    /*------------ GET USER EMAIL TO SEND AN EMAIL TO RESET HIS PASSWORD ------------*/

    /**
     * @Route("/api/user/get/email/{email}", name="api_user_get_email", methods={"POST"})
     */
    public function getEmailToResetPassword(User $user, EncoderInterface $encoder)
    {
        return new JsonResponse(
            $encoder->encode(array('id' => $user->getId()), 'json'), 
            Response::HTTP_OK, 
            ['location' => 'http://localhost:8080/Hypertube/public/api/user/send/email/reset-password/'.$user->getId()], 
            true
        );
    }



                        /*------------ SEND EMAIL TO RESET USER PASSWORD ------------*/

    /**
     * @Route("/api/user/send/email/reset-password/{id}", name="api_user_send_reset_password_mail", methods={"POST"})
     */
    public function sendResetPasswordEmail(User $user, \Swift_Mailer $mailer, Request $request, DecoderInterface $decoder, EncoderInterface $encoder, EntityManagerInterface $manager)
    {
        $data = $request->getContent();
        $data = $decoder->decode($data, 'json');

        if (empty($data['link']))
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'error', 'title' => 
                        'You must enter a link which will be sent in the mail.<br/>Vous devez entrer un lien qui sera envoyé dans l\'email.'))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true);
                
        $link = htmlspecialchars($data['link']);
        $restPasswordKey = mt_rand(1000, 5000).uniqid().mt_rand(10000, 15000);
        $message = new \Swift_Message('Password resetting - Réinitialisation mot de passe');
        $france_logo = $message->embed(\Swift_Image::fromPath('images-emails/france.png'));
        $usa_logo = $message->embed(\Swift_Image::fromPath('images-emails/united-states.png'));

        $message
            ->setFrom(['hypertube-ad@gmail.com' => 'Hypertube'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->renderView(
                    'emails/reset-password.html.twig',
                    [
                        'france_logo' => $france_logo,
                        'usa_logo' => $usa_logo,
                        'firstname' => $user->getFirstname(),
                        'lastname' => $user->getLastname(),
                        'link' => $link.$restPasswordKey
                    ]
                ),
                'text/html'
            )
        ;
        if ($mailer->send($message)) {
            $user->setForgottenPasswordKey($restPasswordKey);
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse("", Response::HTTP_OK, [], true);
        }
        else {
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'email', 
                        'title' => 'An error occurred and the password reset email could not be sent.<br/>Une erreur s\'est produite et l\'email de réinitialisation du mot de passe n\'a pas pu être envoyé.'))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );
        }
    }





                        /*------------ CHECK THE RESET PASSWORD KEY TO RESET USER PASSWORD ------------*/

    /**
     * @Route("/api/user/check/reset-password-key/{id}", name="api_user_check_reset_password_key", methods={"PUT"})
     */
    public function checkResetPasswordKey(User $user, Request $request, DecoderInterface $decoder, EncoderInterface $encoder, EntityManagerInterface $manager, ValidatorInterface $validator, SerializerInterface $serializer, EncoderFactoryInterface $encoderPassword)
    {
        $error_key = null;
        $errors = null;
        $data = $request->getContent();
        $data_check = $decoder->decode($data, 'json');
        $encoderPassword = $encoderPassword->getEncoder($user);
        $tmp_user = new User;

        $serializer->deserialize(
            $data, User::class, 
            'json', 
            [
                'groups' => 'reset_password',
                'object_to_populate' => $tmp_user,
                'disable_type_enforcement' => true
            ]
        );

        if (empty($data['reset_password_key']))
            return new JsonResponse(
                $encoder->encode(array('violations' => array(
                    array('propertyPath' => 'error',
                    'title' => 'You must enter the reset password key which must be verified.<br/>Vous devez entrer la clé de réinitialisation du mot de passe qui doit être vérifiée.'))),
                    'json'
                ),
                Response::HTTP_BAD_REQUEST,
                [],
                true);

        if ($data_check['reset_password_key'] !== $user->getForgottenPasswordKey())
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'reset_password_key', 
                        'title' => 'The reset password key does not match or has already been used.<br/>La clé de réinitialisation du mot de passe ne correspond pas ou à déjà été utilisée.'))),
                    'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );
        

        if ($encoderPassword->isPasswordValid($user->getPassword(), $tmp_user->getPassword(), null))
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'password', 
                        'title' => 'The password is the old one, please change it.<br/>Le mot de passe correspond à l\'ancien, veuillez le modifier.'))),
                    'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );

        $errors = $validator->validate($tmp_user, null, ['reset_password']);

        if (count($errors)) {
            $errorsJson = $serializer->serialize($errors, 'json');
            return new JsonResponse($errorsJson, Response::HTTP_BAD_REQUEST, [], true);
        }
        
        $user->setPassword(htmlspecialchars($tmp_user->getPassword()));
        $passwordEncoded = $encoderPassword->encodePassword($user->getPassword(), null);
        $user->setPassword($passwordEncoded);

        $user->setForgottenPasswordKey(mt_rand(1000, 5000).uniqid().mt_rand(10000, 15000));

        $manager->persist($user);
        $manager->flush();
        
        return new JsonResponse("", Response::HTTP_OK, [], true);
    }



    /**
     * @Route("/api/user/login", name="api_user_login", methods={"POST"})
     */
    public function newTokenAction(Request $request, DecoderInterface $decoder, EncoderFactoryInterface $encoderPassword, JWTTokenManagerInterface $JWTManager, AuthenticationSuccessHandler $authenticationSuccessHandler, UserRepository $repository)
    {
        $data = $request->getContent();
        $data_check = $decoder->decode($data, 'json');

        if (empty($data_check['email']) || empty($data_check['password']))
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'email',
                        'title' => 'The email and password must be filled in.<br/>L\'email et le mot de passe doivent être renseignés.'))),
                    'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );

        $user = $repository->findOneBy(['email' => $data_check['email']]);

        if (!$user)
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'email',
                        'title' => 'The email does not exist.<br/>L\'email est inexistant.'))),
                    'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );

        $encoderPassword = $encoderPassword->getEncoder($user);
        $password = $encoderPassword->isPasswordValid($user->getPassword(), $data_check['password'], null);
        if (!$password)
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'email',
                        'title' => 'The password is wrong.<br/>Le mot de passe est faux.'))),
                    'json'
                ), 
                Response::HTTP_BAD_REQUEST,
                [], 
                true
            );
            
        return $authenticationSuccessHandler->handleAuthenticationSuccess($user);
    }
    

    /**
    * @Route("/api/user/getUser", name="api_user_get_user", methods={"POST"})
    */
    public function getUserWithToken(TokenStorageInterface $token, EncoderInterface $encoder)
    {
        $user = $token->getToken()->getUser();

        return new JsonResponse(
            $encoder->encode(
                array(
                    'id' => $user->getId(),
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'profilePicture' => $user->getprofilePicture(),
                    'language' => $user->getLanguage()
                ),
                'json'
            ),
            Response::HTTP_OK,
            [],
            true
        );
    }


/*------------------------------------------------------------ UPDATING ------------------------------------------------------------*/



                    /*------------ UPDATE USER INFORMATION ------------*/

    /**
     * @Route("/api/user/update/info/{id}", name="api_user_update_info", methods={"PUT"})
     */
    public function editUser(User $user, EntityManagerInterface $manager, SerializerInterface $serializer, Request $request, ValidatorInterface $validator, DecoderInterface $decoder, EncoderInterface $encoder, NormalizerInterface $normalizer)
    {
        $forbidden_fields = null;
        $data = $request->getContent();
        $data_check = $decoder->decode($data, 'json');
        $serializer->deserialize($data, User::class, 'json', ['object_to_populate' => $user]);

        if (!empty($data_check['password']) ||
            !empty($data_check['profilePicture']) ||
            !empty($data_check['activeAccount']) ||
            !empty($data_check['activeAccountKey']) ||
            !empty($data_check['language']) ||
            !empty($data_check['forgottenPasswordKey'])
        )
            $forbidden_fields = array(
                'propertyPath' => 'forbidden_field', 
                'title' => 'Only "firstname", "lastname", "Username" and "email" fields must be completed.'
            );

        $errors = $validator->validate($user, null, ['update']);

        if (count($errors) || $forbidden_fields) {
            if ($forbidden_fields) {
                $errors = $normalizer->normalize($errors);
                array_push($errors['violations'], $forbidden_fields);
                return new JsonResponse($encoder->encode($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
            } else {
                $errorsJson = $serializer->serialize($errors, 'json');
                return new JsonResponse($errorsJson, Response::HTTP_BAD_REQUEST, [], true);
            }

        } else {
            $user->setFirstname(htmlspecialchars($user->getFirstname()));
            $user->setLastname(htmlspecialchars($user->getLastname()));
            $user->setUsername(htmlspecialchars($user->getUsername()));
            $user->setEmail(htmlspecialchars($user->getEmail()));

            $manager->persist($user);
            $manager->flush();
            return new JsonResponse(
                "",
                Response::HTTP_OK,
                ['location' => 'http://localhost:8080/Hypertube/public/api/user/update/picture/'.$user->getId()],
                true
            );
        }
    }



                    /*------------ UPDATE USER IMAGE ------------*/

    /**
     * @Route("/api/user/update/picture/{id}", name="api_user_update_picture", methods={"POST"})
     */
    public function editUserPicture(User $user, EncoderInterface $encoder, EntityManagerInterface $manager)
    {
        $error = null;      
        $valid_extensions = array('image/jpeg', 'image/png');
        $error_case = array(
            'You must select a profile picture.',
            'The file is not a picture.',
            'The picture is too large (max: 40mo).',
            'A problem occurred, and the picture could not be considered.',
            'Only ".jpg-.jpeg" and ".png" images are allowed.'
        );
        if (empty($_FILES['profile_picture']))
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'profile_picture', 
                        'title' => $error_case[0]))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true
            );

        else {
            $picture = $_FILES['profile_picture'];
            if ($picture['error'] == 1) $error = 2;                
            else if ($picture['error'] =! 0 && $picture['error'] =! 1) $error = 3;
            else if (!in_array($picture['type'], $valid_extensions)) $error = 4;
            else if (!in_array(mime_content_type($picture['tmp_name']), $valid_extensions)) $error = 1;
        }
        if (!empty($error)) {
            return new JsonResponse(
                $encoder->encode(
                    array('violations' => array(array(
                        'propertyPath' => 'profile_picture', 
                        'title' => $error_case[$error]))
                    ), 'json'
                ), 
                Response::HTTP_BAD_REQUEST, 
                [], 
                true
            );

        } else {
            $picture_extension = explode('.', $picture['name']);
            $picture_extension = strtolower(end($picture_extension));
            $picture_new_name = uniqid().".".$picture_extension;
            $picture_destination = $_SERVER['DOCUMENT_ROOT']."/Hypertube/public/Profile-pictures/".$picture_new_name;

            move_uploaded_file($picture['tmp_name'], $picture_destination);
            $current_picture_path = $user->getProfilePicture();
            $current_picture_path = explode('/', $current_picture_path);
            $current_picture_path = end($current_picture_path);

            unlink($_SERVER['DOCUMENT_ROOT']."/Hypertube/public/Profile-pictures/".$current_picture_path);

            $user->setProfilePicture('http://localhost:8080/Hypertube/public/Profile-pictures/'.$picture_new_name);
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse("", Response::HTTP_CREATED, [], true);
        }       
    }



                    /*------------ UPDATE USER PASSWORD ------------*/
    /**
     * @Route("/api/user/update/password/{id}", name="api_user_update_password", methods={"PUT"})
     */
    public function editPassword(User $user, EncoderInterface $encoder, Request $request, DecoderInterface $decoder, SerializerInterface $serializer, ValidatorInterface $validator, NormalizerInterface $normalizer, EntityManagerInterface $manager, EncoderFactoryInterface $encoderPassword)
    {
        $forbidden_fields = null;
        $empty_oldPassword = null;

        $data = $request->getContent();
        $currentPassword = $user->getPassword();
        $data_check = $decoder->decode($data, 'json');
        $serializer->deserialize($data, User::class, 'json', ['object_to_populate' => $user]);

        $encoderPassword = $encoderPassword->getEncoder($user);

        if (empty($data_check['oldPassword']))
            $empty_oldPassword = array('propertyPath' => 'oldPassword', 'title' => 'You must enter your current password.');
        else if (!($encoderPassword->isPasswordValid($currentPassword, $data_check['oldPassword'], null)))
            $empty_oldPassword = array('propertyPath' => 'oldPassword', 'title' => 'Your password is incorrect.');

        if (!empty($data_check['id']) ||
            !empty($data_check['firstname']) ||
            !empty($data_check['lastname']) ||
            !empty($data_check['Username']) ||
            !empty($data_check['email']) ||
            !empty($data_check['activeAccount']) ||
            !empty($data_check['activeAccountKey']) ||
            !empty($data_check['language']) ||
            !empty($data_check['profilePicture']) ||
            !empty($data_check['forgottenPasswordKey'])
        )
            $forbidden_fields = array('propertyPath' => 'forbidden_field', 'title' => 'Only "firstname", "lastname", "Username" and "email" fields must be completed.');
            
        $errors = $validator->validate($user, null, ['update_password']);

        if (count($errors) || $forbidden_fields || $empty_oldPassword) {
            if ($forbidden_fields || $empty_oldPassword) {
                $errors = $normalizer->normalize($errors);
                if ($forbidden_fields)
                    array_push($errors['violations'], $forbidden_fields);
                if ($empty_oldPassword)
                    array_push($errors['violations'], $empty_oldPassword);
                return new JsonResponse($encoder->encode($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
            } else {
                $errorsJson = $serializer->serialize($errors, 'json');
                return new JsonResponse($errorsJson, Response::HTTP_BAD_REQUEST, [], true);
            }

        } else {
            $user->setPassword(htmlspecialchars($user->getPassword()));
            $user->setPassword($encoderPassword->encodePassword($user->getPassword(), null));
            $manager->persist($user);
            $manager->flush();
            return new JsonResponse("", Response::HTTP_OK, [], true);
        }
    }
}