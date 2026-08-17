# Pokémon type icons

The 18 SVG files in this directory come from the official Pokémon type chart:

https://sg.portal-pokemon.com/game/type-chart/

They were downloaded from the chart's `/public_assets/images/type/type{1..18}.svg` assets on 2026-08-17 and renamed to match PokéAPI's canonical English type slugs.

The files under `glyphs/` retain the official white symbol paths while removing the source files' square background. The catalog renders these transparent glyphs inside smaller circles using the exact background colors from the source SVGs.
