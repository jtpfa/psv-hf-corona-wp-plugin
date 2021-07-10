<?php
require_once('pdf_creator/fpdf.php');

class PDF extends FPDF
{
    protected $columnss = array();    // array of columns anchors
    protected $textprops = array();    // array of text anchors
    protected $template = 1;            // current template number
    protected $flag_template = -1;            // flag pointing at current template number - if <= 0, regular FPDF processing
    protected $templates = array();    // array containing templates
    protected $max_cols = 20;            // maximum number of columns width for a column anchor

// -----------------------------------------------------

    function LoadTemplate(): int
    {
        setlocale(LC_TIME, 'de_DE');
        date_default_timezone_set('Europe/Berlin');

        $footer_left = "PSV Herford Badminton: Herford, " . date("d.m.Y H:i");

        $lines = array(
            "SetDrawColor (64, 64, 64);",
            "SetFillColor (200, 255, 160);",
            "SetLineWidth (0.5);",
            "Line (10, 280, 410, 280);",
            "SetFont (\"Courier\", \"I\", 10);",
            "Text (10, 290, \"$footer_left\");",
            "SetTextProp (\"FOOTRNB2\", 398, 290, -1, -1, 0, 0, 0,\"Courier\", \"I\", 9);",
            "SetTextColor (0, 0, 0);",
            "SetFont (\"Times\", \"B\", 24);",
            "Text (180, 15,\"Anwesenheitsliste\");",
            "SetColumns  (\"COLSWDTH\", 122, 38, 167, 38, 38);",
            "SetTextProp (\"ROW0COL0\", 5, 25, -1, 8, 0, 0, 0, \"Arial\", \"I\", 11);",
            "SetTextProp (\"ROW1COL0\", 5, 34, -1, 6, 0, 0, 0, \"Courier\", \"B\", 9);"
        );

        $this->flag_template = $this->template;
        $flag_page = false;
        if ($this->page <= 0) {
            $flag_page = true;
            $this->page = 1;    // simulate first page to get around basic FPDF error when no page yet exists
        }

        $this->templates[$this->template] = "";        // Set current template string to empty string
        $num_line = 0;
        foreach ($lines as $line) {
            $num_line++;
            $line = trim($line);
            if (strlen($line) <= 0) {
                continue;
            }
            $cc = substr($line, 0, 1);
            if (($cc == ";") || ($cc == "*") || ($cc == "/")) {
                continue;
            }    // Ignore comments

            $pattern = "/([A-Za-z0-9\s]+)[(].*[)];/";
            if (preg_match($pattern, $line, $regs)) {
                $func = trim($regs[1]);
            } else {
                print ("  ** Unrecognized instruction format at line $num_line\n");
                print ("     => $line\n");
                continue;
            }

            switch ($func) {

                case "SetTextProp":
                    $format = $func . " (\"%[^\"]%*c, %f, %f, %f, %f, %d, %d, %d, \"%[^\"]%*c, \"%[^\"]%*c, %f)";
                    $txtp = sscanf($line, $format);
                    $id_txtp = $txtp [0];
                    $this->textprops[$id_txtp] = array_combine(
                        array('px', 'py', 'ix', 'iy', 'fr', 'fg', 'fb', 'fam', 'sty', 'fsz'),
                        array_slice($txtp, 1));
                    break;

                case "SetColumns":
                    for ($jj = 0; $jj < $this->max_cols; $jj++) {
                        if ($jj > 0) {
                            $format .= ",";
                        } else {
                            $format = "$func (\"%[^\"]%*c,";
                        }
                        $format .= "%f";
                    }
                    $format .= ")";
                    $colls = sscanf($line, $format);
                    $columns = array();
                    for ($jj = 0, $id_cols = $colls[0]; $jj < $this->max_cols; $jj++) {
                        $columns [$jj] = -1;   // negative number if columns width is not specified
                        if (isset ($colls [$jj + 1])) {
                            $columns [$jj] = $colls[$jj + 1];
                        }
                    }
                    $this->columnss[$id_cols] = $columns;
                    break;

                default:
                    if (method_exists($this, $func)) {
                        $bufr = "\$this->" . $line;
                        eval ($bufr);
                    } else {
                        print ("  ** Unrecognized instruction <$func> at line $num_line\n");
                    }
                    break;

            }

        }

        if ($flag_page) {
            $this->page = 0;        // reset Page number in case it has been forced to 1
            $flag_page = false;
        }
        $ii = $this->template;
        $this->template++;
        $this->flag_template = -1;
        return ($ii);
    }

// -----------------------------------------------------

    function IncludeTemplate($num_template)
    {
        $this->PageInfo[$this->page]['templates'][] = $num_template;
    }

// -----------------------------------------------------

    function ApplyTextProp($id_txtp, $text)
    {
        if (!isset ($this->textprops[$id_txtp])) {
            print ("  -- Warning: unknown text anchor [$id_txtp]\n");
            return (false);
        }

        $txtp = $this->textprops[$id_txtp];
        $this->SetFont($txtp['fam'], $txtp['sty'], $txtp['fsz']);
        $this->SetTextColor($txtp['fr'], $txtp['fg'], $txtp['fb']);
        $this->SetXY($txtp['px'], $txtp['py']);
        if (strlen($text) > 0) {
            $this->Text($txtp['px'], $txtp['py'], $text);
        }
        return ($txtp);
    }

    /* ------------------------------------------------------- */

    function GetColls($id_cols)
    {
        if (!isset ($this->columnss[$id_cols])) {
            print ("  -- Warning: unknown column anchor '$id_cols]\n");
            return (false);
        }

        return ($this->columnss[$id_cols]);
    }

    /* ------------------------------------------------------- */

    function _puttemplates()
    {

        $nb = $this->template - 1;
        if ($nb > 0) {
            $this->obj_templates = array();
            for ($n = 1; $n <= $nb; $n++) {
                $this->_putstreamobject($this->templates[$n]);
                $this->obj_templates [$n] = $this->n;
            }
        }

    }

    /* ------------------------------------------------------- */

    protected function _putpage($n)
    {
// ==============================================
//   Part identical to original FPDF processing
// ==============================================
        $this->_newobj();
        $this->_put('<</Type /Page');
        $this->_put('/Parent 1 0 R');
        if (isset($this->PageInfo[$n]['size']))
            $this->_put(sprintf('/MediaBox [0 0 %.2F %.2F]', $this->PageInfo[$n]['size'][0], $this->PageInfo[$n]['size'][1]));
        if (isset($this->PageInfo[$n]['rotation']))
            $this->_put('/Rotate ' . $this->PageInfo[$n]['rotation']);
        $this->_put('/Resources 2 0 R');
        if (isset($this->PageLinks[$n])) {
            // Links
            $annots = '/Annots [';
            foreach ($this->PageLinks[$n] as $pl) {
                $rect = sprintf('%.2F %.2F %.2F %.2F', $pl[0], $pl[1], $pl[0] + $pl[2], $pl[1] - $pl[3]);
                $annots .= '<</Type /Annot /Subtype /Link /Rect [' . $rect . '] /Border [0 0 0] ';
                if (is_string($pl[4]))
                    $annots .= '/A <</S /URI /URI ' . $this->_textstring($pl[4]) . '>>>>';
                else {
                    $l = $this->links[$pl[4]];
                    if (isset($this->PageInfo[$l[0]]['size']))
                        $h = $this->PageInfo[$l[0]]['size'][1];
                    else
                        $h = ($this->DefOrientation == 'P') ? $this->DefPageSize[1] * $this->k : $this->DefPageSize[0] * $this->k;
                    $annots .= sprintf('/Dest [%d 0 R /XYZ 0 %.2F null]>>', $this->PageInfo[$l[0]]['n'], $h - $l[1] * $this->k);
                }
            }
            $this->_put($annots . ']');
        }
        if ($this->WithAlpha)
            $this->_put('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');

// ========================================
//   Part specific to templates extension
// ========================================
        $str_templates = "";
        $end_brckt = "";
        if (count($this->PageInfo[$n]['templates']) > 0) {
            $str_templates = "[";
            $templates_num = $this->PageInfo[$n]['templates'];
            foreach ($templates_num as $template_num) {
                $str_templates .= $this->obj_templates[$template_num] . ' 0 R ';
            }
            $end_brckt = "]";
        }
        $this->_put('/Contents ' . $str_templates . ($this->n + 1) . ' 0 R' . "$end_brckt>>");

// ====================================
//   Back to original FPDF processing
// ====================================

        $this->_put('endobj');
        // Page content
        if (!empty($this->AliasNbPages))
            $this->pages[$n] = str_replace($this->AliasNbPages, $this->page, $this->pages[$n]);
        $this->_putstreamobject($this->pages[$n]);
    }

    /* ------------------------------------------------------- */

    function _putpages()
    {
        $this->_puttemplates();
        parent::_putpages();
    }

    /* ------------------------------------------------------- */

    function _out($s)
    {
        if ($this->flag_template > 0) {
            $this->templates[$this->template] .= $s . "\n";
        } else {
            parent::_out($s);
        }
    }

    /* ------------------------------------------------------- */

    function _beginpage($orientation, $size, $rotation)
    {
        parent::_beginpage($orientation, $size, $rotation);
        $this->PageInfo[$this->page]['templates'] = array();

    }

    function checkPageBreak(): bool
    {
        return $this->GetY()+15>=$this->PageBreakTrigger;
    }
}

?>
