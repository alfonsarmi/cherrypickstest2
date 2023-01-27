<?php

namespace Zinkers\Bundle\FantasyBundle\Controller\Api\v3\Users;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\View;
use Nelmio\ApiDocBundle\Annotation\Model;
use FOS\RestBundle\Controller\FOSRestController;
use SimpleBus\SymfonyBridge\Bus\CommandBus;
use SimpleBus\SymfonyBridge\Bus\EventBus;
use Swagger\Annotations as SWG;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Zinkers\Component\Users\Command\SetUserRegionCommand;
use Zinkers\Component\Users\Command\UpdateUserRegionCommand;
use Zinkers\Component\Regions\Model\Region;
use Zinkers\Component\Users\Command\UpdateUserFromDataCommand;
use Zinkers\Component\Users\CommandHandler\SetUserRegionHandler;
use Zinkers\Component\Users\Event\UserUpdated;
use Zinkers\Component\Users\Exception\InvalidArgumentException;
use Zinkers\Component\Users\Model\User;
use Zinkers\Component\Users\Model\UserRepositoryInterface;

/**
 * @method User getUser()
 */
class UserController extends FOSRestController
{




    /**
     * @Rest\Route("/v3/user/prepare", methods={"POST"}) another test
     * @View()
     *
     * @return User
     *
     * @SWG\Post(
     *     tags={"users"},
     *     security={{"Bearer": {}}},
     *     description="
             - It tries to match profileId in token with existing user.
             - It checks given email with the one stored in the DSP.",
     *     @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          required=true,
     *          type="json",
     *          @SWG\Schema(
     *              required={"email"},
     *              @SWG\Property(property="email", type="string", example="user@email.fake")
     *          )
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Matches given `email` with `profileId` in the DSP token."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Request has not the required parameters."
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Invalid credentials: missing profileId in token payload or invalid token."
     *     ),
     *     @SWG\Response(
     *         response="409",
     *         description="Email not verified: given token has email verified set to false from DSP."
     *     ),
     * )
     */
    public function dspLoginAction()
    {
        return null;
    }

    /**
     * @Rest\Route("/v3/user/me", methods={"GET"})
     * @View(
     *     serializerGroups={"v3DetailUser", "v3DetailRegion", "basicDivisionObject", "detailedDivisionObject"},
     *     serializerEnableMaxDepthChecks=true
     * )
     * @param Request $request
     * @param UserRepositoryInterface $userRepository
     * @param EventBus $eventBus
     * @return User
     *
     * @SWG\Get(
     *     tags={"users"},
     *     security={{"Bearer": {}}},
     *     description="
    - It lists User data.",
     *     @SWG\Response(
     *          response="200",
     *          description="User data",
     *          @Model(
     *              type=User::class,
     *              groups={"v3DetailUser", "v3DetailRegion", "basicDivisionObject", "detailedDivisionObject"}
     *          )
     *     )
     * )
     */
    public function getUserByIdAction(Request $request, UserRepositoryInterface $userRepository, EventBus $eventBus)
    {
        return $this->checkLocaleUser($this->getUser(), $request, $userRepository, $eventBus);
    }

    /**
     * @Rest\Route("/v3/user/me", methods={"PUT"})
     * @View(serializerGroups={"v3DetailUser"}, serializerEnableMaxDepthChecks=true)
     * @param Request $request
     * @return User
     *
     * @SWG\Put(
     *     tags={"users"},
     *     security={{"Bearer": {}}},
     *     description="
    - It updates User data.",
     *     @SWG\Parameter(
     *          name="userData",
     *          required=true,
     *          description="manager name",
     *          in="body",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="managerName", type="string"),
     *              @SWG\Property(property="acceptsLaLigaCommunications", type="boolean"),
     *              @SWG\Property(property="acceptsMarcaCommunications", type="boolean"),
     *              @SWG\Property(property="acceptsThirdPartyCommunications", type="boolean"),
     *              @SWG\Property(property="favouriteTeamId", type="integer"),
     *              @SWG\Property(property="country", type="string"),
     *              @SWG\Property(property="regionId", type="integer")
     *          )
     *     ),
     *     @SWG\Response(
     *          response="200",
     *          description="User data",
     *          @Model(type=User::class, groups={"v3DetailUser"})
     *     ),
     * )
     */
    public function updateUserDataAction(Request $request)
    {
        /**
         * @var $user User;
         */
        $user = $this->getUser();

        $userData = json_decode($request->getContent(), true);

        $userData = array_intersect_key($userData, [
            'managerName' => null,
            'acceptsLaLigaCommunications' => false,
            'acceptsMarcaCommunications' => false,
            'acceptsThirdPartyCommunications' => false,
            'favouriteTeamId' => null,
            'country' => null,
            'avatar' => null,
            'regionId' => null
        ]);

        if (null !== $user->getRegion() && isset($userData['regionId'])) {
            throw new InvalidArgumentException(
                'regionId is not a valid argument. The user already has a region.'
            );
        }

        $updateUserFromDataCommand = new UpdateUserFromDataCommand($userData, $user);
        $this->get('command_bus')->handle($updateUserFromDataCommand);

        return $this->get('fantasy.user_repository')
            ->getLastCreatedOrSaved();
    }

    /**
     * @Rest\Route("/v3/user/region/{region}", methods={"PUT"})
     * @View(serializerGroups={"v3DetailUser"}, serializerEnableMaxDepthChecks=true)
     * @param Region $region
     * @param CommandBus $commandBus
     * @return User
     *
     * @SWG\Put(
     *     tags={"users"},
     *     security={{"Bearer": {}}},
     *     description="
    - It updates User data.",
     *     @SWG\Parameter(
     *          name="userData",
     *          required=true,
     *          description="manager name",
     *          in="body",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="managerName", type="string"),
     *              @SWG\Property(property="acceptsLaLigaCommunications", type="boolean"),
     *              @SWG\Property(property="acceptsMarcaCommunications", type="boolean"),
     *              @SWG\Property(property="acceptsThirdPartyCommunications", type="boolean"),
     *              @SWG\Property(property="favouriteTeamId", type="integer"),
     *              @SWG\Property(property="country", type="string"),
     *              @SWG\Property(property="regionId", type="integer")
     *          )
     *     ),
     *     @SWG\Response(
     *          response="200",
     *          description="User data",
     *          @Model(type=User::class, groups={"v3DetailUser"})
     *     ),
     * )
     */
    public function updateUserRegionAction(Region $region, CommandBus $commandBus)
    {
        $user = $this->getUser();

        $updateRegionDataCommand = new UpdateUserRegionCommand($region, $user);

        $commandBus->handle($updateRegionDataCommand);

        return $user;
    }

    /**
     * @Rest\Route("/v3/user/forgot-password", methods={"POST"})
     *
     * @SWG\Post(
     *     tags={"users"},
     *     description="
    🚨 as DSP issue is solved this endpoint does nothing. And always returns 204 🚨

    - It changes user's password to accomplish new password policies.
    - It requests user prepare to DSP's identity API.",
     *     @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          required=true,
     *          type="json",
     *          @SWG\Schema(
     *              required={"email"},
     *              @SWG\Property(property="email", type="string", example="user@email.fake")
     *          )
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="All actions were done successfully."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="
    - 070.03.01: email is missing.
    - 070.03.02: email does not exist in WBO.
    - 070.03.03: WBO error changing password."
     *     )
     * )
     *
     * @return null
     */
    public function forgotPasswordAction()
    {
        return null;
    }

    /**
     * @Rest\Get("/v3/user/count")
     *
     * @SWG\Get(
     *     tags={"users"},
     *     description="Counts active users.",
     *     @SWG\Response(
     *          response="200",
     *          description="Number of active users."
     *     )
     * )
     * @param UserRepositoryInterface $userRepository
     * @return JsonResponse
     */
    public function countUsersAction(UserRepositoryInterface $userRepository)
    {
        $users = $userRepository->countActiveUsers();
        return new JsonResponse([
            'users' => $users,
        ]);
    }

    /**
     * @Rest\Route("/v3/user/region-by-country", methods={"POST"})
     * @View(serializerEnableMaxDepthChecks=true)
     *
     * @SWG\Put(
     *     tags={"users"},
     *     security={{"Bearer": {}}},
     *     description="Update User region by country code",
     *     @SWG\Parameter(
     *      name="countryData",
     *      required=true,
     *      description="country code",
     *      in="body",
     *      @SWG\Schema(
     *          type="object",
     *          @SWG\Property(property="countryCode", type="string")
     *      )
     *     ),
     *     @SWG\Response(
     *          response="200",
     *          description="Updated User region",
     *          @Model(type=Region::class)
     *      )
     * )
     *
     * @param Request $request
     * @param SetUserRegionHandler $setUserRegionHandler
     * @return Region
     */
    public function updateUserRegionByCountryAction(
        Request $request,
        SetUserRegionHandler $setUserRegionHandler
    ) {
        $user = $this->getUser();
        $dataCountry = json_decode($request->getContent(), true);

        return $setUserRegionHandler->handle(new SetUserRegionCommand($user, $dataCountry['countryCode']));
    }

    /**
     * @return User
     */
    protected function getHydratedUser()
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $this->get('leagues.service.team_manager')->hydrateTeamsWithLeagues($user->getTeams());
        }

        return $user;
    }

    /**
     * @param $user
     * @param $request
     * @param $userRepository
     * @param $eventBus
     * @return mixed
     */
    protected function checkLocaleUser(
        User $user,
        Request $request,
        UserRepositoryInterface $userRepository,
        EventBus $eventBus
    ) {
        if ($user->getLocale() !== $request->getLocale()) {
            $user->setLocale($request->getLocale());
            $userRepository->update($user);

            $eventBus->handle(new UserUpdated($user));
        }

        return $user;
    }
}
