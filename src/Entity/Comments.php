<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CommentsRepository")
 */
class Comments
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $commentator;

    /**
     * @ORM\Column(type="integer")
     */
    private $commentated;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $comment;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommentator(): ?int
    {
        return $this->commentator;
    }

    public function setCommentator(int $commentator): self
    {
        $this->commentator = $commentator;

        return $this;
    }

    public function getCommentated(): ?int
    {
        return $this->commentated;
    }

    public function setCommentated(int $commentated): self
    {
        $this->commentated = $commentated;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
