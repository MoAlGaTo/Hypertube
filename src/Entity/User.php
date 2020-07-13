<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @UniqueEntity(
 *      fields="email",
 *      message="This email is already used.<br/>Cette email est déjà utilisé."
 * )
 * @UniqueEntity(
 *      fields="username",
 *      message="This username is already used.<br/>Ce nom d'utilisateur est déjà utilisé."
 * )
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("registration")
     * @Assert\NotBlank(
     *      message="You must enter a first name.<br/>Vous devez entrer un prénom.",
     *      groups={"registration"}
     * )
     * @Assert\Length(
     *      min = 2,
     *      max = 50,
     *      minMessage = "Your first name must be at least {{ limit }} characters long.<br/>Votre prénom doit comporter au moins {{ limit }} caractères.",
     *      maxMessage = "Your first name cannot be longer than {{ limit }} characters.<br/>Votre prénom ne doit pas comporter plus de {{ limit }} caractères.",
     *      allowEmptyString = true,
     *      groups={"registration"}
     * )
     * @Assert\Regex(
     *     pattern="/^[^!@#$%^&*(),.;?"":{}\\\[\]\/|<>0-9\t]{1,}$/",
     *     message="Your first name should contain letters only.<br/>Votre prénom ne doit contenir que des lettres.",
     *     groups={"registration"}
     * )
     */
    private $firstname;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("registration")
     * @Assert\NotBlank(
     *      message="You must enter a last name.<br/>Vous devez entrer un nom.",
     *      groups={"registration"}
     * )
     * @Assert\Length(
     *      min = 2,
     *      max = 50,
     *      minMessage = "Your last name must be at least {{ limit }} characters long.<br/>Votre nom doit comporter au moins {{ limit }} caractères",
     *      maxMessage = "Your last name cannot be longer than {{ limit }} characters.<br/>Votre nom ne doit pas comporter plus de {{ limit }} caractères.",
     *      allowEmptyString = true,
     *      groups={"registration"}
     * )
     * @Assert\Regex(
     *     pattern="/^[^!@#$%^&*(),.;?"":{}\\\[\]\/|<>0-9\t]{1,}$/",
     *     message="Your last name should contain letters only.<br/>Votre nom ne doit contenir que des lettres.",
     *      groups={"registration"}
     * )
     */
    private $lastname;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("registration")
     * @Assert\NotBlank(
     *      message="You must enter a username.<br/>Vous devez entrer un nom d'utilisateur.",
     *      groups={"registration"}
     * )
     * @Assert\Length(
     *      min=2,
     *      max=30,
     *      minMessage = "Your username must be at least {{ limit }} characters long.<br/>Votre nom d'utilisateur doit comporter au moins {{ limit }} caractères.",
     *      maxMessage = "Your username cannot be longer than {{ limit }} characters.<br/>Votre nom d'utilisateur ne doit pas comporter plus de {{ limit }} caractères.",
     *      allowEmptyString = true,
     *      groups={"registration"}
     * )
     * @Assert\Regex(
     *     pattern="/(?!^\d+$)^.+$/",
     *     message="Your username must not contain numbers only.<br/>Votre nom d'utilisateur ne doit pas contenir que des chiffres.",
     *     groups={"registration"}
     * )
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups("registration")
     * @Assert\NotBlank(
     *      message="You must enter an email.<br/>Vous devez entrer un email.",
     *      groups={"registration"}
     * )
     * @Assert\Email(
     *      mode="html5",
     *      message = "This value is not a valid email.<br/>Cette valeur n'est pas un email valide.",
     *      groups={"registration"}
     * )
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"registration", "reset_password"})
     * @Assert\NotBlank(
     *      message="You must enter a password.<br/>Vous devez enter un mot de passe.",
     *      groups={"registration", "reset_password"}
     * )
     * @Assert\Regex(
     *     pattern="/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*(),.;?"":{}\[\]|\/\\<>]).{6,}$/",
     *     message="Your password must have at least 1 upper case, 1 lower case, 1 number and 1 special character.<br/>Votre mot de passe doit contenir au moins 1 majuscule, une minuscule, 1 chiffre et un caractère special.",
     *     groups={"registration", "reset_password"}
     * )
     * @Assert\Length(
     *      min=6,
     *      max = 50,
     *      minMessage = "Your password must be at least {{ limit }} characters long.<br/>Votre mot de passe doit comporter au moins {{ limit }} caractères.",
     *      maxMessage = "Your password cannot be longer than {{ limit }} characters.<br/>Votre mot de passe ne doit pas comporter plus de {{ limit }} caractères.",
     *      allowEmptyString = true,
     *      groups={"registration", "reset_password"}
     * )
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $profilePicture;

    /**
     * @ORM\Column(type="integer")
     */
    private $activeAccount;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $activeAccountKey;

    /**
     * @ORM\Column(type="boolean")
     */
    private $language;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $forgottenPasswordKey;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(string $profilePicture): self
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getActiveAccount(): ?int
    {
        return $this->activeAccount;
    }

    public function setActiveAccount(int $activeAccount): self
    {
        $this->activeAccount = $activeAccount;

        return $this;
    }

    public function getActiveAccountKey(): ?string
    {
        return $this->activeAccountKey;
    }

    public function setActiveAccountKey(string $activeAccountKey): self
    {
        $this->activeAccountKey = $activeAccountKey;

        return $this;
    }

    public function getLanguage(): ?bool
    {
        return $this->language;
    }

    public function setLanguage(bool $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getForgottenPasswordKey(): ?string
    {
        return $this->forgottenPasswordKey;
    }

    public function setForgottenPasswordKey(string $forgottenPasswordKey): self
    {
        $this->forgottenPasswordKey = $forgottenPasswordKey;

        return $this;
    }

    /**
     * Returns the roles granted to the user.
     *
     *     public function getRoles()
     *     {
     *         return ['ROLE_USER'];
     *     }
     *
     * Alternatively, the roles might be stored on a ``roles`` property,
     * and populated in any number of different ways when the user object
     * is created.
     *
     * @return (Role|string)[] The user roles
     */
    public function getRoles()
    {
        return ['ROLE_USER'];
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string|null The salt
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * Returns the username used to authenticate the user.
     *
     * @return string The username
     */
    // public function getUsername()
    // {
    //     return $this->getUsername();
    // }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(){}

}
