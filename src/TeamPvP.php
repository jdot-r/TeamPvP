<?php 
2  
3 namespace MCrafters\TeamPvP; 
4  
5 use pocketmine\plugin\PluginBase; 
6 use pocketmine\utils\TextFormat as Color; 
7 use pocketmine\utils\Config; 
8 use pocketmine\event\Listener; 
9 use pocketmine\event\entity\EntityDamageEvent; 
10 use pocketmine\event\entity\EntityDamageByEntityEvent; 
11 use pocketmine\event\player\PlayerInteractEvent; 
12 use pocketmine\event\player\PlayerDeathEvent; 
13 use pocketmine\math\Vector3; 
14 use pocketmine\level\Position; 
15 use pocketmine\command\Command; 
16 use pocketmine\command\CommandSender; 
17 use pocketmine\Player; 
18 use pocketmine\block\Block; 
19 use pocketmine\item\Item; 
20 use pocketmine\block\WallSign; 
21 use pocketmine\block\PostSign; 
22 use pocketmine\scheduler\ServerScheduler; 
23  
24 class TeamPvP extends PluginBase implements Listener 
25 { 
26  
27     // Teams 
28     public $reds = []; 
29     public $blues = []; 
30     public $gameStarted = false; 
31     public $yml; 
32  
33  
34     public function onEnable() 
35     { 
36         // Initializing config files 
37         $this->saveResource("config.yml"); 
38         $yml = new Config($this->getDataFolder() . "config.yml", Config::YAML); 
39         $this->yml = $yml->getAll(); 
40  
41         $this->getLogger()->debug("Config files have been saved!"); 
42          
43     $level = $this->yml["sign_world"]; 
44      
45     if(!$this->getServer()->isLevelGenerated($level)){ 
46       $this->getLogger()->error("The level you used on the config ( " . $level . " ) doesn't exist! stopping plugin..."); 
47       $this->getServer()->getPluginManager()->disablePlugin($this->getServer()->getPluginManager()->getPlugin("MTeamPvP")); 
48     } 
49      
50     if(!$this->getServer()->isLevelLoaded($level)){ 
51       $this->getServer()->loadLevel($level); 
52     } 
53  
54         $this->getServer()->getScheduler()->scheduleRepeatingTask(new Tasks\SignUpdaterTask($this), 15); 
55         $this->getServer()->getPluginManager()->registerEvents($this, $this); 
56         $this->getServer()->getLogger()->info(Color::BOLD . Color::GOLD . "M" . Color::AQUA . "TeamPvP " . Color::GREEN . "Enabled" . Color::RED . "!"); 
57     } 
58  
59     public function isFriend($p1, $p2) 
60     { 
61         if ($this->getTeam($p1) === $this->getTeam($p2) && $this->getTeam($p1) !== false) { 
62             return true; 
63         } else { 
64             return false; 
65         } 
66     } 
67  
68     // isFriend 
69     public function getTeam($p) 
70     { 
71         if (in_array($p, $this->reds)) { 
72             return "red"; 
73         } elseif (in_array($p, $this->blues)) { 
74             return "blue"; 
75         } else { 
76             return false; 
77         } 
78     } 
79  
80     public function setTeam($p, $team) 
81     { 
82         if (strtolower($team) === "red") { 
83             if (count($this->reds) < 5) { 
84                 if ($this->getTeam($p) === "blue") { 
85                     unset($this->blues[array_search($p, $this->blues)]); 
86                 } 
87                 array_push($this->reds, $p); 
88                 $this->getServer()->getPlayer($p)->setNameTag("§c§l" . $p); 
89                 $this->getServer()->getPlayer($p)->teleport(new Vector3($this->yml["waiting_x"], $this->yml["waiting_y"], $this->yml["waiting_z"])); 
90                 return true; 
91             } elseif (count($this->blues) < 5) { 
92                 $this->setTeam($p, "blue"); 
93             } else { 
94                 return false; 
95             } 
96         } elseif (strtolower($team) === "blue") { 
97             if (count($this->blues) < 5) { 
98                 if ($this->getTeam($p) === "red") { 
99                     unset($this->reds[array_search($p, $this->reds)]); 
100                 } 
101                 array_push($this->blues, $p); 
102                 $this->getServer()->getPlayer($p)->setNameTag("§b§l" . $p); 
103                 $this->getServer()->getPlayer($p)->teleport(new Vector3($this->yml["waiting_x"], $this->yml["waiting_y"], $this->yml["waiting_z"])); 
104                 return true; 
105             } elseif (count($this->reds) < 5) { 
106                 $this->setTeam($p, "red"); 
107             } else { 
108                 return false; 
109             } 
110         } 
111     } 
112  
113     public function removeFromTeam($p, $team) 
114     { 
115         if (strtolower($team) == "red") { 
116             unset($this->reds[array_search($p, $this->reds)]); 
117             return true; 
118         } elseif (strtolower($team) == "blue") { 
119             unset($this->blues[array_search($p, $this->blues)]); 
120             return true; 
121         } 
122     } 
123  
124     public function onInteract(PlayerInteractEvent $event) 
125     { 
126         $p = $event->getPlayer(); 
127         $teams = array("red", "blue"); 
128         if ($event->getBlock()->getX() === $this->yml["sign_join_x"] && $event->getBlock()->getY() === $this->yml["sign_join_y"] && $event->getBlock()->getZ() === $this->yml["sign_join_z"]) { 
129             if (count($this->blues) !== 5 and count($this->reds) !== 5) { 
130                 $this->setTeam($p->getName(), $teams[array_rand($teams, 1)]); 
131                 $s = new GameManager(); 
132                 $s->run(); 
133             } else { 
134                 $p->sendMessage($this->yml["teams_are_full_message"]); 
135             } 
136         } 
137     } 
138  
139     public function onEntityDamage(EntityDamageEvent $event) 
140     { 
141         if ($event instanceof EntityDamageByEntityEvent) { 
142             if ($event->getEntity() instanceof Player) { 
143                 if ($this->isFriend($event->getDamager()->getName(), $event->getEntity()->getName()) && $this->gameStarted == true) { 
144                     $event->setCancelled(true); 
145                     $event->getDamager()->sendMessage(str_replace("{player}", $event->getPlayer()->getName(), $this->yml["hit_same_team_message"])); 
146                 } 
147  
148                 if ($this->isFriend($event->getDamager()->getName(), $event->getEntity()->getName())) { 
149                     $event->setCancelled(true); 
150                 } 
151             } 
152         } 
153     } 
154  
155  
156     public function onDeath(PlayerDeathEvent $event) 
157     { 
158         if ($this->getTeam($event->getEntity()->getName()) == "red" && $this->gameStarted == true) { 
159             $this->removeFromTeam($event->getEntity()->getName(), "red"); 
160             $event->getEntity()->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn()); 
161         } elseif ($this->getTeam($event->getEntity()->getName()) == "blue" && $this->gameStarted == true) { 
162             $this->removeFromTeam($event->getEntity()->getName(), "blue"); 
163             $event->getEntity()->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn()); 
164         } 
165         foreach ($this->blues as $b) { 
166             foreach ($this->reds as $r) { 
167                 if (count($this->reds) == 0 && $this->gameStarted == true) { 
168  
169                     $this->getServer()->getPlayer($b)->getInventory()->clearAll(); 
                     $this->removeFromTeam($b, "blue"); 
                     $this->getServer()->getPlayer($b)->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn()); 
                     $this->getServer()->broadcastMessage("Blue Team won TeamPvP!"); 
                 } elseif (count($this->blues) == 0 && $this->gameStarted == true) { 
                     $this->getServer()->getPlayer($r)->getInventory()->clearAll(); 
                     $this->removeFromTeam($r, "red"); 
                     $this->getServer()->getPlayer($r)->teleport($this->getServer()->getLevelByName($this->yml["spawn_level"])->getSafeSpawn()); 
                     $this->gameStarted = false; 
                 } 
             } 
         } 
     } 
 }//class 
