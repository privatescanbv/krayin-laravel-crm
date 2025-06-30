#!/bin/bash

mkdir -p ./html/resources
asciidoctor \
  -D ./modules \
  -o ../html/index.html \
  -r asciidoctor-diagram \
  -a stylesheet=mb.css \
  -a imagesdir=./resources \
  index.adoc && \
cp  ./modules/domain/pages/*.png ./html/resources | true &&
cp  ./modules/domain/pages/*.pdf ./html | true &&
cp  ./*.svg ./html | true &&
cp  ./mb.css ./html/ | true
