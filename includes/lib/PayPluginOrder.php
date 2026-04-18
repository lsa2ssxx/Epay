<?php
namespace lib;

/**
 * 支付通道 / 统计列表等与「插件声明的 types 顺序」对齐的排序工具。
 */
class PayPluginOrder {

	/**
	 * 同一插件内：优先 USDT 系（调用值 usdt / usdt.*），其次 USDC 系，其余按插件 types 原有顺序。
	 */
	public static function stableAssetGroup(string $typename): int {
		$t = strtolower(trim($typename));
		if($t === 'usdt' || strpos($t, 'usdt.') === 0){
			return 0;
		}
		if($t === 'usdc' || strpos($t, 'usdc.') === 0){
			return 1;
		}
		return 2;
	}

	/**
	 * 将插件 types 列表重排为：先全部 usdt.*（保持原相对顺序），再 usdc.*，再其它。
	 *
	 * @param string[] $tokens
	 * @return string[]
	 */
	public static function reorderTokensStableFirst(array $tokens): array {
		$b = [[], [], []];
		foreach($tokens as $t){
			if($t === ''){
				continue;
			}
			$b[self::stableAssetGroup($t)][] = $t;
		}
		return array_merge($b[0], $b[1], $b[2]);
	}

	/** @return array<string, array<string,bool>> typename => [ pluginDir => true ] */
	public static function typeNameToPluginsMap(): array {
		$typeToPlugins = [];
		foreach(Plugin::getList() as $pn){
			if(!$pn){
				continue;
			}
			$cfg = Plugin::getConfig($pn);
			if(!$cfg || empty($cfg['name'])){
				continue;
			}
			$types = $cfg['types'] ?? [];
			if(!is_array($types)){
				$types = explode(',', (string)$types);
			}
			$tokens = array_unique(array_filter(array_map('trim', $types)));
			foreach($tokens as $t){
				if($t === ''){
					continue;
				}
				if(!isset($typeToPlugins[$t])){
					$typeToPlugins[$t] = [];
				}
				$typeToPlugins[$t][$cfg['name']] = true;
			}
		}
		return $typeToPlugins;
	}

	/**
	 * @param string[] $names
	 * @param array<string, array<string,bool>> $map
	 * @return string[]
	 */
	public static function orderedTypeNames(array $names, array $map): array {
		$nameSet = array_fill_keys($names, true);
		$common = [];
		$exclusive = [];
		$none = [];
		foreach($names as $n){
			$c = isset($map[$n]) ? count($map[$n]) : 0;
			if($c >= 2){
				$common[] = $n;
			}elseif($c === 1){
				$keys = array_keys($map[$n]);
				$pn = $keys[0];
				if(!isset($exclusive[$pn])){
					$exclusive[$pn] = [];
				}
				$exclusive[$pn][$n] = true;
			}else{
				$none[] = $n;
			}
		}
		$common = array_unique($common);
		usort($common, function($a, $b){
			$ga = self::stableAssetGroup($a);
			$gb = self::stableAssetGroup($b);
			if($ga !== $gb){
				return $ga <=> $gb;
			}
			return strcmp($a, $b);
		});
		$ordered = [];
		foreach($common as $n){
			if(isset($nameSet[$n])){
				$ordered[] = $n;
			}
		}

		$pluginNames = array_filter(Plugin::getList());
		sort($pluginNames, SORT_STRING);
		foreach($pluginNames as $pn){
			if(empty($exclusive[$pn])){
				continue;
			}
			$cfg = Plugin::getConfig($pn);
			$tokens = [];
			if($cfg){
				$types = $cfg['types'] ?? [];
				if(!is_array($types)){
					$types = explode(',', (string)$types);
				}
				$tokens = array_unique(array_filter(array_map('trim', $types)));
				$tokens = self::reorderTokensStableFirst($tokens);
			}
			foreach($tokens as $t){
				if(!empty($exclusive[$pn][$t])){
					$ordered[] = $t;
					unset($exclusive[$pn][$t]);
				}
			}
			if(!empty($exclusive[$pn])){
				$left = array_keys($exclusive[$pn]);
				usort($left, function($a, $b){
					$ga = self::stableAssetGroup($a);
					$gb = self::stableAssetGroup($b);
					if($ga !== $gb){
						return $ga <=> $gb;
					}
					return strcmp($a, $b);
				});
				foreach($left as $t){
					$ordered[] = $t;
				}
			}
		}

		usort($none, function($a, $b){
			$ga = self::stableAssetGroup($a);
			$gb = self::stableAssetGroup($b);
			if($ga !== $gb){
				return $ga <=> $gb;
			}
			return strcmp($a, $b);
		});
		foreach($none as $n){
			$ordered[] = $n;
		}
		return $ordered;
	}

	/**
	 * @param array<int, array<string,mixed>> $typeRows pre_type 行（需含 id,name）
	 * @return array<int, array<string,mixed>>
	 */
	public static function sortEnabledPayTypeRows(array $typeRows): array {
		if($typeRows === []){
			return [];
		}
		$rowsByName = [];
		foreach($typeRows as $r){
			$n = $r['name'];
			if(!isset($rowsByName[$n])){
				$rowsByName[$n] = [];
			}
			$rowsByName[$n][] = $r;
		}
		$nameOrder = self::orderedTypeNames(array_keys($rowsByName), self::typeNameToPluginsMap());
		$out = [];
		foreach($nameOrder as $n){
			$chunk = $rowsByName[$n];
			usort($chunk, function($a, $b){
				return intval($a['id']) - intval($b['id']);
			});
			foreach($chunk as $row){
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, array<string,mixed>> $list channelList 行（需含 id, plugin, typename）
	 * @return array<int, array<string,mixed>>
	 */
	public static function sortChannelRows(array $list): array {
		if($list === []){
			return [];
		}
		$pluginNames = array_filter(Plugin::getList());
		sort($pluginNames, SORT_STRING);
		$pluginRank = [];
		$r = 0;
		foreach($pluginNames as $p){
			$pluginRank[$p] = ++$r;
		}
		$typeOrderCache = [];
		$getIdx = function($plugin, $typename) use (&$typeOrderCache){
			$plugin = (string)$plugin;
			$typename = (string)$typename;
			if(!isset($typeOrderCache[$plugin])){
				$typeOrderCache[$plugin] = [];
				$cfg = Plugin::getConfig($plugin);
				if($cfg){
					$types = $cfg['types'] ?? [];
					if(!is_array($types)){
						$types = explode(',', (string)$types);
					}
					$tokens = array_unique(array_filter(array_map('trim', $types)));
					$i = 0;
					foreach($tokens as $t){
						$typeOrderCache[$plugin][$t] = $i++;
					}
				}
			}
			if(isset($typeOrderCache[$plugin][$typename])){
				return $typeOrderCache[$plugin][$typename];
			}
			return 9999;
		};
		usort($list, function($a, $b) use ($pluginRank, $getIdx){
			$pa = isset($a['plugin']) ? (string)$a['plugin'] : '';
			$pb = isset($b['plugin']) ? (string)$b['plugin'] : '';
			$ra = ($pa === '') ? 99999 : (isset($pluginRank[$pa]) ? $pluginRank[$pa] : 9999);
			$rb = ($pb === '') ? 99999 : (isset($pluginRank[$pb]) ? $pluginRank[$pb] : 9999);
			if($ra !== $rb){
				return $ra <=> $rb;
			}
			$ta = (string)($a['typename'] ?? '');
			$tb = (string)($b['typename'] ?? '');
			$ga = self::stableAssetGroup($ta);
			$gb = self::stableAssetGroup($tb);
			if($ga !== $gb){
				return $ga <=> $gb;
			}
			$ia = $getIdx($pa, $ta);
			$ib = $getIdx($pb, $tb);
			if($ia !== $ib){
				return $ia <=> $ib;
			}
			return intval($b['id']) <=> intval($a['id']);
		});
		return $list;
	}
}
