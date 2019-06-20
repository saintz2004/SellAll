<?php

declare(strict_types=1);

namespace AndreasHGK\SellAll;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\item\Item;
use onebone\economyapi\EconomyAPI;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Player;

class SellAll extends PluginBase{

	const CFGVERSION = 1.3;

	/** @var Config $cfg */
	protected $cfg;
	/** @var array $msg */
	protected $msg;
	/** @var Config $msgfile */
	protected $msgfile;

	protected function getSellPrice(Item $item) : ?int{
		return $this->cfg[$item->getID() . ":" . $item->getDamage()] ?? $this->cfg[$item->getID()] ?? null;
	}

	protected function isSellable(Item $item) : bool{
		return $this->getSellPrice($item) !== null ? true : false;
	}

	public function onEnable() : void{
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->cfg = $this->getConfig()->getAll();
		$this->saveResource("messages.yml");;
		$this->msgfile = new Config($this->getDataFolder() . "messages.yml", Config::YAML, []);
		$this->msg = $this->msgfile->getAll();
		if(!isset($this->cfg["cfgversion"])){
			$this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
		}elseif($this->cfg["cfgversion"] != self::CFGVERSION){
			$this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
		}
		if(!isset($this->msg["cfgversion"])){
			$this->getLogger()->critical("messages version outdated! please regenerate your messages file or this plugin might not work correctly.");
		}elseif($this->msg["cfgversion"] != self::CFGVERSION){
			$this->getLogger()->critical("messages version outdated! please regenerate messages file config or this plugin might not work correctly.");
		}
	}

	protected function replaceVars($str, array $vars) : string{
		foreach($vars as $key => $value){
			$str = str_replace("{" . $key . "}", $value, $str);
		}
		return $str;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$sender instanceof Player){
			$sender->sendMessage(TextFormat::colorize("&cPlease execute this command in-game"));
			return false;
		}
		switch($command->getName()){
			case "sell":
				if(!$sender->hasPermission("sell.command")){
					$sender->sendMessage(TextFormat::colorize($this->msg["error.permission"]));
					return false;
				}
				if(isset($args[0])){
					switch(strtolower($args[0])){
						case "hand":
							if(!$sender->hasPermission("sell.hand")){
								$sender->sendMessage(TextFormat::colorize($this->msg["error.permission"]));
								return false;
							}
							$item = $sender->getInventory()->getItemInHand();
							if(isset($this->cfg[$item->getID() . ":" . $item->getDamage()])){
								$price = $this->cfg[$item->getID() . ":" . $item->getDamage()];
								$count = $item->getCount();
								$totalprice = $price * $count;
								EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$totalprice);
								$item->setCount($item->getCount() - (int)$count);
								$sender->getInventory()->setItemInHand($item);
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array("AMOUNT" => strval($count), "ITEMNAME" => $item->getName(), "MONEY" => strval($totalprice)))));
							}elseif(isset($this->cfg[$item->getID()])){
								$price = $this->cfg[$item->getID()];
								$count = $item->getCount();
								$totalprice = $price * $count;
								EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$totalprice);
								$item->setCount($item->getCount() - (int)$count);
								$sender->getInventory()->setItemInHand($item);
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array("AMOUNT" => strval($count), "ITEMNAME" => $item->getName(), "MONEY" => strval($totalprice)))));
							}
							$sender->sendMessage(TextFormat::colorize($this->msg["error.not-found"]));
							break;
						case "all":
							if(!$sender->hasPermission("sell.all")){
								$sender->sendMessage(TextFormat::colorize($this->msg["error.permission"]));
								return false;
							}
							$item = $sender->getInventory()->getItemInHand();
							$inventory = $sender->getInventory();
							$contents = $inventory->getContents();
							if(isset($this->cfg[$item->getID() . ":" . $item->getDamage()])){
								$price = $this->cfg[$item->getID() . ":" . $item->getDamage()];
								$count = 0;
								foreach($contents as $slot){
									if($slot->getID() == $item->getId()){
										$count = $count + $slot->getCount();
										$inventory->remove($slot);
									}
								}
								$inventory->sendContents($sender);
								$totalprice = $count * $price;
								EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$totalprice);
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array("AMOUNT" => strval($count), "ITEMNAME" => $item->getName(), "MONEY" => strval($totalprice)))));
							}elseif(isset($this->cfg[$item->getID()])){
								$price = $this->cfg[$item->getID()];
								$count = 0;
								foreach($contents as $slot){
									if($slot->getID() == $item->getId()){
										$count = $count + $slot->getCount();
										$inventory->remove($slot);
									}
								}
								$inventory->sendContents($sender);
								$totalprice = $count * $price;
								EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$totalprice);
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell"], array("AMOUNT" => strval($count), "ITEMNAME" => $item->getName(), "MONEY" => strval($totalprice)))));
							}
							$sender->sendMessage(TextFormat::colorize($this->msg["error.not-found"]));
							break;
						case "inv":
						case "inventory":
							if(!$sender->hasPermission("sell.inv")){
								$sender->sendMessage(TextFormat::colorize($this->msg["error.permission"]));
								return false;
							}
							$inv = $sender->getInventory()->getContents();
							$revenue = 0;
							foreach($inv as $item){
								if(isset($this->cfg[$item->getID() . ":" . $item->getDamage()])){
									$revenue = $revenue + ($item->getCount() * $this->cfg[$item->getID() . ":" . $item->getDamage()]);
									$sender->getInventory()->remove($item);
								}elseif(isset($this->cfg[$item->getID()])){
									$revenue = $revenue + ($item->getCount() * $this->cfg[$item->getID()]);
									$sender->getInventory()->remove($item);
								}
							}
							if($revenue <= 0){
								$sender->sendMessage(TextFormat::colorize($this->msg["error.no.sellables"]));
								return false;
							}
							EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$revenue);
							$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["success.sell.inventory"], array("MONEY" => strval($revenue)))));
							break;
						case "reload":
							if($sender->hasPermission("sell.reload")){
								$this->reloadConfig();
								$this->cfg = $this->getConfig()->getAll();
								$this->msgfile = new Config($this->getDataFolder() . "messages.yml", Config::YAML, []);
								$this->msg = $this->msgfile->getAll();
								if(!isset($this->cfg["cfgversion"])){
									$this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
								}elseif($this->cfg["cfgversion"] != self::CFGVERSION){
									$this->getLogger()->critical("config version outdated! please regenerate your config or this plugin might not work correctly.");
								}
								if(!isset($this->msg["cfgversion"])){
									$this->getLogger()->critical("messages version outdated! please regenerate your messages file or this plugin might not work correctly.");
								}elseif($this->msg["cfgversion"] != self::CFGVERSION){
									$this->getLogger()->critical("messages version outdated! please regenerate messages file config or this plugin might not work correctly.");
								}
								$sender->sendMessage(TextFormat::colorize($this->msg["reload"]));
							}else{
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array("ARGS" => $this->listArguments()))));
							}
							break;
						default:
							if(!$sender->hasPermission("sell.inv")){
								$sender->sendMessage(TextFormat::colorize($this->msg["error.permission"]));
								return false;
							}
							if(array_key_exists($args[0], $this->cfg["groups"])){
								$group = $this->cfg["groups"][$args[0]];
								$inv = $sender->getInventory()->getContents();
								$revenue = 0;
								foreach($inv as $item){
									if(isset($this->cfg[$item->getID()])){
										if(in_array($item->getId(), $group["items"]) || in_array($item->getName(), $group["items"])){
											if(isset($this->cfg[$item->getID() . ":" . $item->getDamage()])){
												$revenue = $revenue + ($item->getCount() * $this->cfg[$item->getID() . ":" . $item->getDamage()]);
												$sender->getInventory()->remove($item);
											}elseif(isset($this->cfg[$item->getID()])){
												$revenue = $revenue + ($item->getCount() * $this->cfg[$item->getID()]);
												$sender->getInventory()->remove($item);
											}
										}
									}
								}
								if($revenue <= 0){
									$sender->sendMessage(TextFormat::colorize($group["failed"]));
									return false;
								}
								EconomyAPI::getInstance()->addMoney($sender->getName(), (int)$revenue);
								$sender->sendMessage(TextFormat::colorize($this->replaceVars($group["success"], array("MONEY" => strval($revenue)))));
							}
							$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array("ARGS" => $this->listArguments()))));
					}
				}
				$sender->sendMessage(TextFormat::colorize($this->replaceVars($this->msg["error.argument"], array("ARGS" => $this->listArguments()))));
				break;
			default:
				break;
		}
		return true;
	}

	protected function listArguments() : string{
		$seperator = $this->msg["separator"];
		$args = "hand" . $seperator . "all" . $seperator . "inv";
		foreach($this->cfg["groups"] as $name => $group){
			$args = $args . $seperator . $name;
		}
		return strval($args);
	}
}