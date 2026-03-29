<?php

declare(strict_types=1);

namespace \Drupal\pds_sync\\Net\Paullieberman\Bike;

class Ride 
{
	/** @var string */
	private ?string $route = null;

	/** @var int */
	private ?int $miles = null;

	/** @var string */
	private ?string $bike = null;

	/**
	 * @var string
	 * @format datetime
	 */
	private ?string $date = null;

	/**
	 * @var string
	 * @format datetime
	 */
	private ?string $createdAt = null;

	/** @var string */
	private ?string $body = null;

	/**
	 * @var string
	 * @format uri
	 */
	private ?string $url = null;


	/**
	 * Get the value of $route.
	 *
	 * @return string
	 */
	public function getRoute(): string
	{
		return $this->route;
	}


	/**
	 * Set the value of $route
	 *
	 * @param string $route
	 * @return self
	 */
	public function setRoute(string $route): self
	{
		$this->route = $route;
		return $this;
	}


	/**
	 * Get the value of $miles.
	 *
	 * @return int
	 */
	public function getMiles(): int
	{
		return $this->miles;
	}


	/**
	 * Set the value of $miles
	 *
	 * @param int $miles
	 * @return self
	 */
	public function setMiles(int $miles): self
	{
		$this->miles = $miles;
		return $this;
	}


	/**
	 * Get the value of $bike.
	 *
	 * @return string
	 */
	public function getBike(): string
	{
		return $this->bike;
	}


	/**
	 * Set the value of $bike
	 *
	 * @param string $bike
	 * @return self
	 */
	public function setBike(string $bike): self
	{
		$this->bike = $bike;
		return $this;
	}


	/**
	 * Get the value of $date.
	 *
	 * @return string
	 */
	public function getDate(): string
	{
		return $this->date;
	}


	/**
	 * Set the value of $date
	 *
	 * @param string $date
	 * @return self
	 */
	public function setDate(string $date): self
	{
		$this->date = $date;
		return $this;
	}


	/**
	 * Get the value of $createdAt.
	 *
	 * @return string
	 */
	public function getCreatedAt(): string
	{
		return $this->createdAt;
	}


	/**
	 * Set the value of $createdAt
	 *
	 * @param string $createdAt
	 * @return self
	 */
	public function setCreatedAt(string $createdAt): self
	{
		$this->createdAt = $createdAt;
		return $this;
	}


	/**
	 * Get the value of $body.
	 *
	 * @return string
	 */
	public function getBody(): string
	{
		return $this->body;
	}


	/**
	 * Set the value of $body
	 *
	 * @param string $body
	 * @return self
	 */
	public function setBody(string $body): self
	{
		$this->body = $body;
		return $this;
	}


	/**
	 * Get the value of $url.
	 *
	 * @return string
	 */
	public function getUrl(): string
	{
		return $this->url;
	}


	/**
	 * Set the value of $url
	 *
	 * @param string $url
	 * @return self
	 */
	public function setUrl(string $url): self
	{
		$this->url = $url;
		return $this;
	}


	public function toArray(): array
	{
		return ['route' => $this->route, 'miles' => $this->miles, 'bike' => $this->bike, 'date' => $this->date, 'createdAt' => $this->createdAt, 'body' => $this->body, 'url' => $this->url];
	}


	public function jsonSerialize(): array
	{
		return $this->toArray();
	}
}
