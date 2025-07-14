#!/bin/bash

mkdir -p ./html/resources
asciidoctor \
  -D ./modules \
  -o ../html/index.html \
  -r asciidoctor-diagram \
  -a stylesheet=mb.css \
  -a imagesdir=/admin/docs/resources \
  -a imagesoutdir=docs/html/resources \
  index.adoc && \
cp  ./*.svg ./html/resources | true &&
cp  ./mb.css ./html/ | true
