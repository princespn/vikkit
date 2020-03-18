<?php

namespace App\EventSubscriber;

use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use FOS\UserBundle\Event\GetResponseNullableUserEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Gomco\DashboardBundle\Entity\User;
use function json_last_error;
use function json_last_error_msg;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class BeforeActionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'convertJsonStringToArray',
            FOSUserEvents::REGISTRATION_INITIALIZE => 'registerationInit',
            FOSUserEvents::REGISTRATION_FAILURE => 'registerationFailed',
            FOSUserEvents::REGISTRATION_SUCCESS =>  ['registerationCompleted', -100],
            FOSUserEvents::RESETTING_SEND_EMAIL_COMPLETED =>  ['resetEmailSendCompleted', -100],
            FOSUserEvents::RESETTING_SEND_EMAIL_INITIALIZE =>  ['checkUserResetStatus', -100],
            FOSUserEvents::RESETTING_RESET_INITIALIZE =>  ['checkUserResetTokenStatus', -100],
            FOSUserEvents::RESETTING_RESET_SUCCESS =>  ['resetPasswordCompleted', -100],
            FOSUserEvents::CHANGE_PASSWORD_SUCCESS =>  ['changePasswordCompleted', -100],
            KernelEvents::RESPONSE =>  ['handleWrongResponse', 100],
        );
    }

    public function registerationInit(GetResponseUserEvent $event){
        $request = $event->getRequest();
        $tempPassword['plainPassword']['first'] = 'tmep';
        $tempPassword['plainPassword']['second'] = 'tmep';
        $request->request->add($tempPassword);
    }

    public function handleWrongResponse(ResponseEvent $event){
        $request = $event->getRequest();
        $response = $event->getResponse();
        if(stripos( $request->get('_route'), 'fos_user' ) !== false
            && $request->getContentType() == 'json'
            && !$response instanceof JsonResponse
        ){
            $event->setResponse(new JsonResponse([
                'message' => $this->getErrorMessage($request->get('_route')),
            ],
                Response::HTTP_BAD_REQUEST
            ));
        }
    }

    public function getErrorMessage($route){
        if($route == 'fos_user_change_password') return 'Error occured, kindly check your current password';
        return  sprintf('Error occured while processing your request, kindly try again or contact support%s', '');
    }

    public function resetPasswordCompleted(FormEvent $event){
        /** @var User $user */
//        $user = $event->getUser();
        $event->setResponse(new JsonResponse([
                'message' => sprintf('Password reset, kindly login%s', '')
            ])
        );
    }

    public function changePasswordCompleted(FormEvent $event){
        /** @var User $user */
//        $user = $event->getUser();
        $event->setResponse(new JsonResponse([
                'message' => sprintf('Password changed%s', '')
            ])
        );
    }

    public function resetEmailSendCompleted(GetResponseUserEvent $event){
        /** @var User $user */
        $user = $event->getUser();
        $event->setResponse(new JsonResponse([
                'message' => sprintf('%s reset email sent', $user->getUsername())
            ])
        );
    }

    public function checkUserResetStatus(GetResponseNullableUserEvent $event){
        /** @var User $user */
        $user = $event->getUser();
        if($user == null) $event->setResponse(new JsonResponse([
                'message' => sprintf('User do not exists')
            ], Response::HTTP_BAD_REQUEST)
        );
        if(null !== $user && $user->isPasswordRequestNonExpired(7200)){
            $event->setResponse(new JsonResponse([
                    'message' => sprintf('Reset email already sent')
                ])
            );
        }
    }

    public function checkUserResetTokenStatus(GetResponseUserEvent $event){
        /** @var User $user */
        $user = $event->getUser();
        if($user == null) $event->setResponse(new JsonResponse([
                'message' => sprintf('User do not exists')
            ], 404)
        );
        if(null !== $user && !$user->isPasswordRequestNonExpired(7200)){
            $event->setResponse(new JsonResponse([
                    'message' => sprintf('Token expired, kindly request token again')
                ], Response::HTTP_BAD_REQUEST)
            );
        }
    }

    public function registerationCompleted(FormEvent $event){
        /** @var User $user */
        $user = $event->getForm()->getData();
        $event->setResponse(new JsonResponse([
            'message' => sprintf('%s registered successfully', $user->getUsername())
            ])
        );
    }

    public function registerationFailed(FormEvent $event){
        $errors = $this->getErrorsFromForm($event->getForm());

        $event->setResponse(new JsonResponse(['message' => $errors], 400));
    }

    public function convertJsonStringToArray(ControllerEvent $event)
    {
        $request = $event->getRequest();
        if ($request->getContentType() != 'json' || !$request->getContent()) {
            return;
        }
        if($request->get('data')) $data = $request->get('data');
        else $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('invalid json body: ' . json_last_error_msg());
        }
        $request->request->add(is_array($data) ? $data : array());
    }

    private function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
//        foreach ($form->all() as $childForm) {
//            if ($childForm instanceof FormInterface) {
//                if ($childErrors = $this->getErrorsFromForm($childForm)) {
//                    $errors[$childForm->getName()] = $childErrors;
//                }
//            }
//        }
        return $errors;
    }


}